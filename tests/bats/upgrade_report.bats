#!/usr/bin/env bats
# Behavioural tests for `scripts/verify-patches.sh --upgrade-report`.
#
# This mode is the one tool standing between an upstream bump and silent patch
# loss, and its failure mode is not a crash — it is a GREEN row for a patch
# that no longer applies. So every state is driven from a synthetic tree whose
# content is chosen to force exactly that state:
#
#   GREEN  the patch applies to this tree
#   BLUE   the change is already in the file
#   AMBER  the target is there but the patch does not apply — upstream moved
#   RED    the target is gone from a component that IS present
#   N/A    the whole component is absent, so nothing was evaluated
#
# The distinction between RED and N/A is what makes the report readable: point
# --tree at a MediaWiki core tarball and eight patches have no component at
# all, which must not be reported as eight losses.
#
# No containers, no network, no composer. `patch`, `grep` and python3 only.

setup() {
    REPO="$BATS_TEST_DIRNAME/../.."
    VERIFY="$REPO/scripts/verify-patches.sh"
    [ -f "$VERIFY" ] || { echo "verify-patches.sh not found at $VERIFY" >&2; return 1; }

    export NO_COLOR=1
    MANIFEST="$BATS_TEST_TMPDIR/manifest"
    TREE="$BATS_TEST_TMPDIR/tree"
    mkdir -p "$MANIFEST" "$TREE/extensions/Present/src" "$TREE/includes"

    # A diff-mode patch: one hunk turning `$a = 1;` into `$a = 2;`.
    cat > "$MANIFEST/demo.patch" <<'PATCH'
--- a/includes/Demo.php
+++ b/includes/Demo.php
@@ -1,3 +1,3 @@
 <?php
-$a = 1;
+$a = 2;
 // end
PATCH

    cat > "$MANIFEST/demo.yaml" <<EOF
id: demo
title: demo diff patch
class: C
mode: diff
target: includes/Demo.php
patch: $MANIFEST/demo.patch
stale: false
why: >-
  synthetic
EOF
}

manifest_only() {
    # Keep exactly the named sidecars, so each test drives one row.
    local keep="$1"
    find "$MANIFEST" -name '*.yaml' ! -name "$keep.yaml" -delete
}

run_report() {
    run env HDP_PATCH_MANIFEST_DIR="$MANIFEST" bash "$VERIFY" --upgrade-report --tree "$TREE"
}

write_demo() {
    printf '<?php\n%s\n// end\n' "$1" > "$TREE/includes/Demo.php"
}

@test "a patch that applies to this tree is GREEN" {
    write_demo '$a = 1;'
    run_report
    [ "$status" -eq 0 ]
    [[ "$output" == *"GREEN "*"demo"* ]]
}

@test "a change already in the file is BLUE, not GREEN" {
    # The upgrade question is 'will this still work', so an already-patched
    # file is not a missing patch — the opposite reading from `verify`.
    write_demo '$a = 2;'
    run_report
    [ "$status" -eq 0 ]
    [[ "$output" == *"BLUE "*"demo"* ]]
}

@test "upstream moving the code is AMBER and fails the report" {
    write_demo '$a = 99; // upstream rewrote this'
    run_report
    [ "$status" -eq 1 ]
    [[ "$output" == *"AMBER "*"demo"* ]]
    [[ "$output" == *"need a human"* ]]
}

@test "a deleted target inside a present component is RED" {
    mkdir -p "$TREE/includes"
    rm -f "$TREE/includes/Demo.php"
    run_report
    [ "$status" -eq 1 ]
    [[ "$output" == *"RED "*"demo"* ]]
    [[ "$output" == *"nowhere to go"* ]]
}

@test "an absent component is N/A — not evaluated, and never a pass" {
    cat > "$MANIFEST/gone.yaml" <<EOF
id: gone
title: patch for an extension this tree does not ship
class: C
mode: diff
target: extensions/Absent/src/Thing.php
patch: $MANIFEST/demo.patch
stale: false
why: >-
  synthetic
EOF
    manifest_only gone
    run_report
    [[ "$output" == *"N/A"*"gone"* ]]
    [[ "$output" == *"not in this tree"* ]]
    # A skip is not a pass: the exit code has to say the answer was partial.
    [ "$status" -eq 1 ]
}

@test "insert mode: anchor present and marker absent is GREEN" {
    cat > "$MANIFEST/ins.yaml" <<'EOF'
id: ins
title: insert patch
class: A
mode: insert
target: extensions/Present/src/Thing.php
anchor: 'setRetries\( 2 \);'
marker: '// HDP marker'
stale: false
why: >-
  synthetic
EOF
    manifest_only ins
    printf '<?php\n$c->setRetries( 2 );\n' > "$TREE/extensions/Present/src/Thing.php"
    run_report
    [ "$status" -eq 0 ]
    [[ "$output" == *"GREEN "*"ins"* ]]
}

@test "insert mode: the anchor gone is AMBER" {
    cat > "$MANIFEST/ins.yaml" <<'EOF'
id: ins
title: insert patch
class: A
mode: insert
target: extensions/Present/src/Thing.php
anchor: 'setRetries\( 2 \);'
marker: '// HDP marker'
stale: false
why: >-
  synthetic
EOF
    manifest_only ins
    printf '<?php\n$c->setRetryPolicy( $p );\n' > "$TREE/extensions/Present/src/Thing.php"
    run_report
    [ "$status" -eq 1 ]
    [[ "$output" == *"AMBER "*"ins"* ]]
    [[ "$output" == *"anchor gone"* ]]
}

@test "insert mode: the marker already present is BLUE" {
    cat > "$MANIFEST/ins.yaml" <<'EOF'
id: ins
title: insert patch
class: A
mode: insert
target: extensions/Present/src/Thing.php
anchor: 'setRetries\( 2 \);'
marker: '// HDP marker'
stale: false
why: >-
  synthetic
EOF
    manifest_only ins
    printf '<?php\n$c->setRetries( 2 );\n// HDP marker\n' > "$TREE/extensions/Present/src/Thing.php"
    run_report
    [ "$status" -eq 0 ]
    [[ "$output" == *"BLUE "*"ins"* ]]
}

@test "an anti-pattern match is RED even when the marker is there" {
    cat > "$MANIFEST/ins.yaml" <<'EOF'
id: ins
title: insert patch
class: A
mode: insert
target: extensions/Present/src/Thing.php
anchor: 'setRetries\( 2 \);'
marker: '// HDP marker'
anti: 'DO NOT SHIP'
stale: false
why: >-
  synthetic
EOF
    manifest_only ins
    printf '<?php\n// HDP marker\n// DO NOT SHIP\n' > "$TREE/extensions/Present/src/Thing.php"
    run_report
    [ "$status" -eq 1 ]
    [[ "$output" == *"RED "*"ins"* ]]
}

@test "a stale patch whose target came back is RED, not silently stale" {
    cat > "$MANIFEST/old.yaml" <<EOF
id: old
title: retired patch
class: C
mode: diff
target: includes/Demo.php
patch: $MANIFEST/demo.patch
stale: true
why: >-
  synthetic
EOF
    manifest_only old
    write_demo '$a = 1;'
    run_report
    [ "$status" -eq 1 ]
    [[ "$output" == *"RED "*"old"* ]]
    [[ "$output" == *"declared stale"* ]]
}

@test "a stale patch whose target is gone stays STALE and passes" {
    cat > "$MANIFEST/old.yaml" <<EOF
id: old
title: retired patch
class: C
mode: diff
target: includes/Demo.php
patch: $MANIFEST/demo.patch
stale: true
why: >-
  synthetic
EOF
    manifest_only old
    rm -f "$TREE/includes/Demo.php"
    run_report
    [ "$status" -eq 0 ]
    [[ "$output" == *"STALE"*"old"* ]]
}

@test "a missing tree is an error, not an empty green report" {
    run env HDP_PATCH_MANIFEST_DIR="$MANIFEST" bash "$VERIFY" --upgrade-report --tree "$BATS_TEST_TMPDIR/nope"
    [ "$status" -eq 2 ]
    [[ "$output" == *"does not exist"* ]]
}
