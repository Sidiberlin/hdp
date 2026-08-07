#!/usr/bin/env python3
"""Drive install.sh under a pty with a faked nvidia-smi and a stub docker.

install.sh reads every answer from /dev/tty (fd 3), so nothing short of a real
terminal exercises it. Each scenario gets a throwaway repo copy, a PATH whose
first entry supplies `nvidia-smi` and `docker`, and a rule table that answers
prompts by what they say rather than by position — so an extra prompt (the
"proceed anyway?" one) does not desynchronise the run.

Asserted per scenario: the wheel tag written to .env, whether the pre-built
image path was refused in favour of a source build, and the driver-compat lines
the operator sees.
"""
import os
import pty
import re
import select
import shutil
import subprocess
import sys
import tempfile

REPO = "/root/hdp"
COPY = [
    "install.sh",
    ".env.example",
    "docker-compose.yml",
    "docker-compose.gpu.yml",
    "docker-compose.prod.yml",
    "docker-compose.prod-gpu.yml",
]

NVIDIA_SMI = r"""#!/bin/sh
# Fake nvidia-smi. CUDA ceiling comes from FAKE_CUDA; FAKE_SMI_STYLE picks
# which of the two spellings of that field the driver package prints.
case "$1" in
  -L) echo "GPU 0: NVIDIA GeForce RTX 3090 (UUID: GPU-deadbeef)"; exit 0 ;;
  --query-gpu=name) echo "NVIDIA GeForce RTX 3090"; exit 0 ;;
  -q)
    [ "$FAKE_SMI_STYLE" = "header-only" ] && exit 0
    echo "    CUDA Version                          : $FAKE_CUDA"
    exit 0 ;;
esac
if [ "$FAKE_SMI_STYLE" = "q-only" ]; then
  echo "Fri Aug  7 12:00:00 2026"
  echo "no table on this driver package"
  exit 0
fi
cat <<EOF
Fri Aug  7 12:00:00 2026
+---------------------------------------------------------------------------+
| NVIDIA-SMI 550.54.14   Driver Version: 550.54.14   CUDA Version: $FAKE_CUDA |
|---------------------------------------------------------------------------|
| GPU  Name        Persistence-M | Bus-Id      Disp.A | Volatile Uncorr. ECC |
|   0  NVIDIA GeForce RTX 3090   | 00000000:01:00.0 O |                  N/A |
+---------------------------------------------------------------------------+
EOF
exit 0
"""

DOCKER = r"""#!/bin/sh
# Stub docker: records every invocation, never touches a daemon.
printf '%s\n' "$*" >> "$HDP_TEST_DOCKER_LOG"
case "$1" in
  --version) echo "Docker version 27.0.0, build stub"; exit 0 ;;
  info) exit 0 ;;
  compose)
    if [ "$2" = "version" ]; then
      [ "$3" = "--short" ] && echo "2.29.7" || echo "Docker Compose version v2.29.7"
      exit 0
    fi
    exit 0 ;;
esac
exit 0
"""

# Matched against everything printed since the previous answer, most specific
# first. Anything unmatched gets a bare Enter, i.e. the offered default.
def rules(gpu_choice="3", proceed_anyway="y", predownload="n"):
    return [
        ("Proceed with GPU mode anyway?", proceed_anyway),
        ("Generate all four automatically", "y"),
        ("Configuration looks right?", "y"),
        ("Pre-download embedding models", predownload),
        ("Start the services now?", "n"),
        ("API key for", "sk-test-key"),
        ("Embeddings", gpu_choice),
    ]


def run(name, *, cuda=None, smi_style="both", gpu_choice="3",
        proceed_anyway="y", with_smi=True, predownload="n", seed_env=None):
    work = tempfile.mkdtemp(prefix="hdp-install-test-")
    repo = os.path.join(work, "repo")
    binp = os.path.join(work, "bin")
    os.makedirs(repo)
    os.makedirs(binp)
    for f in COPY:
        shutil.copy(os.path.join(REPO, f), repo)
    os.chmod(os.path.join(repo, "install.sh"), 0o755)
    if seed_env is not None:
        open(os.path.join(repo, ".env"), "w").write(seed_env)

    if with_smi:
        p = os.path.join(binp, "nvidia-smi")
        open(p, "w").write(NVIDIA_SMI)
        os.chmod(p, 0o755)
    p = os.path.join(binp, "docker")
    open(p, "w").write(DOCKER)
    os.chmod(p, 0o755)

    docker_log = os.path.join(work, "docker.log")
    open(docker_log, "w").close()

    env = dict(os.environ)
    env.update({
        "PATH": binp + ":/usr/bin:/bin",
        "NO_COLOR": "1",
        "FAKE_CUDA": cuda or "",
        "FAKE_SMI_STYLE": smi_style,
        "HDP_TEST_DOCKER_LOG": docker_log,
    })

    answers = rules(gpu_choice, proceed_anyway, predownload)
    pid, fd = pty.fork()
    if pid == 0:
        os.chdir(repo)
        os.execve("/bin/bash", ["bash", "./install.sh"], env)

    out, pending, last = [], "", 0.0
    while True:
        r, _, _ = select.select([fd], [], [], 45)
        if not r:
            break
        try:
            chunk = os.read(fd, 4096)
        except OSError:
            break
        if not chunk:
            break
        text = chunk.decode("utf-8", "replace")
        out.append(text)
        pending += text
        # A prompt is a line that ends without a newline and asks for input.
        tail = pending[-4000:]
        if re.search(r"(: |\]: |\[y/N\]: |\[Y/n\]: )$", tail):
            reply = "\n"
            for needle, value in answers:
                if needle in tail:
                    reply = value + "\n"
                    break
            os.write(fd, reply.encode())
            pending = ""
        last += 1
        if last > 4000:
            break

    _, status = os.waitpid(pid, 0)
    rc = os.waitstatus_to_exitcode(status)
    log = "".join(out)
    envfile = os.path.join(repo, ".env")
    envtext = open(envfile).read() if os.path.exists(envfile) else ""
    dockerlog = open(docker_log).read()
    return dict(name=name, rc=rc, log=log, env=envtext, docker=dockerlog,
                work=work)


def env_get(envtext, key):
    m = re.search(rf"^{re.escape(key)}=(.*)$", envtext, re.M)
    return m.group(1) if m else None


FAILED = []


def check(res, label, cond, detail=""):
    mark = "PASS" if cond else "FAIL"
    print(f"  [{mark}] {label}" + (f"   {detail}" if detail and not cond else ""))
    if not cond:
        FAILED.append(f"{res['name']}: {label}")


def show(res):
    print(f"\n=== {res['name']} (exit {res['rc']}) ===")


def strip(s):
    return re.sub(r"\x1b\[[0-9;]*m", "", s)


def main():
    # 1 — modern driver: cu124 and the published GPU image is fine.
    r = run("driver CUDA 13.0 -> cu124, pre-built OK", cuda="13.0")
    show(r)
    log = strip(r["log"])
    check(r, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(r, "HAYSTACK_DEVICE=gpu", env_get(r["env"], "HAYSTACK_DEVICE") == "gpu",
          str(env_get(r["env"], "HAYSTACK_DEVICE")))
    check(r, "HAYSTACK_CUDA_VERSION=cu124",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu124",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))
    check(r, "reports the detected CUDA version",
          "Driver supports CUDA 13.0 — using PyTorch cu124 build" in log)
    check(r, "keeps the pre-built path (prod-gpu.yml in the printed command)",
          "docker-compose.prod-gpu.yml" in log and "--build" not in log)

    # 2 — 12.0 driver: cu124 wheels would not load, so cu118 + source build.
    r = run("driver CUDA 12.0 -> cu118, pre-built refused", cuda="12.0")
    show(r)
    log = strip(r["log"])
    check(r, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(r, "HAYSTACK_CUDA_VERSION=cu118",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu118",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))
    check(r, "HAYSTACK_DEVICE=gpu", env_get(r["env"], "HAYSTACK_DEVICE") == "gpu")
    check(r, "says why cu118", "Driver supports CUDA 12.0 — using PyTorch cu118 build" in log)
    check(r, "warns the pre-built image cannot be used",
          "pre-built GPU image carries CUDA 12.4" in log)
    check(r, "prints the source-build command, not the prod-gpu one",
          "docker-compose.gpu.yml" in log and "--build" in log
          and "docker-compose.prod-gpu.yml" not in log)
    check(r, "toolkit-verify image is one this driver can run",
          "nvidia/cuda:11.8.0-base-ubuntu22.04" in log
          and "nvidia/cuda:12.4.0-base" not in log)

    # 3 — 11.8 exactly: the oldest driver that still gets a GPU build.
    r = run("driver CUDA 11.8 -> cu118 (boundary)", cuda="11.8")
    show(r)
    check(r, "HAYSTACK_CUDA_VERSION=cu118",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu118",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))
    check(r, "HAYSTACK_DEVICE=gpu", env_get(r["env"], "HAYSTACK_DEVICE") == "gpu")

    # 4 — 12.4 exactly: the boundary the other way.
    r = run("driver CUDA 12.4 -> cu124 (boundary)", cuda="12.4")
    show(r)
    check(r, "HAYSTACK_CUDA_VERSION=cu124",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu124",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))
    check(r, "keeps the pre-built path", "docker-compose.prod-gpu.yml" in strip(r["log"]))

    # 5 — driver older than every wheel: GPU refused, CPU instead.
    r = run("driver CUDA 11.2 -> too old, falls back to CPU", cuda="11.2")
    show(r)
    log = strip(r["log"])
    check(r, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(r, "HAYSTACK_DEVICE=cpu", env_get(r["env"], "HAYSTACK_DEVICE") == "cpu",
          str(env_get(r["env"], "HAYSTACK_DEVICE")))
    check(r, "did not write a wheel tag over the default",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu124")
    check(r, "names the driver's ceiling", "supports only CUDA 11.2" in log)
    check(r, "explains the failure it avoided",
          "CUDA driver version is insufficient" in log)
    check(r, "review line records the reason",
          "driver supports only CUDA 11.2" in log)
    check(r, "no GPU compose override in the printed command",
          "docker-compose.gpu.yml" not in log
          and "docker-compose.prod-gpu.yml" not in log)
    check(r, "menu no longer defaults to the option it will refuse",
          "choice [1]: 3" in log)
    check(r, "does not claim to be using the GPU before refusing it",
          "Using the detected NVIDIA GPU" not in log)

    # 6 — nvidia-smi present, CUDA version unreadable.
    r = run("CUDA version unparseable -> cu124 + warning", cuda="N/A")
    show(r)
    log = strip(r["log"])
    check(r, "HAYSTACK_CUDA_VERSION=cu124",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu124",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))
    check(r, "HAYSTACK_DEVICE=gpu", env_get(r["env"], "HAYSTACK_DEVICE") == "gpu")
    check(r, "warns it is guessing",
          "Could not determine the CUDA driver version. Defaulting to cu124." in log)
    check(r, "names the override", "HAYSTACK_CUDA_VERSION=cu118 in .env" in log)
    check(r, "still uses the pre-built path",
          "docker-compose.prod-gpu.yml" in log)

    # 7 — the -q fallback: driver package with no header table.
    r = run("CUDA read from `nvidia-smi -q` fallback", cuda="11.8",
            smi_style="q-only")
    show(r)
    check(r, "HAYSTACK_CUDA_VERSION=cu118",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu118",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))

    # 8 — no nvidia-smi at all, operator insists on GPU.
    r = run("no GPU visible, user proceeds anyway -> cu124", with_smi=False)
    show(r)
    log = strip(r["log"])
    check(r, "HAYSTACK_DEVICE=gpu", env_get(r["env"], "HAYSTACK_DEVICE") == "gpu",
          str(env_get(r["env"], "HAYSTACK_DEVICE")))
    check(r, "HAYSTACK_CUDA_VERSION=cu124",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu124")
    check(r, "warns it is guessing",
          "Could not determine the CUDA driver version" in log)

    # 9 — CPU choice on a GPU box: nothing CUDA-related is written.
    r = run("CPU chosen on a CUDA 13.0 box", cuda="13.0", gpu_choice="1")
    show(r)
    log = strip(r["log"])
    check(r, "HAYSTACK_DEVICE=cpu", env_get(r["env"], "HAYSTACK_DEVICE") == "cpu",
          str(env_get(r["env"], "HAYSTACK_DEVICE")))
    check(r, "HAYSTACK_CUDA_VERSION untouched at cu124",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu124")
    check(r, "no GPU override in the printed command",
          "docker-compose.gpu.yml" not in log)

    # 10 — the model pre-download must run through the same compose files the
    # stack will use. On the forced-build path that means gpu.yml, not the
    # prod-gpu override whose image this host cannot run.
    r = run("pre-download uses the build path when cu118 is forced",
            cuda="12.0", predownload="y")
    show(r)
    runs = [ln for ln in r["docker"].splitlines() if " run " in ln]
    check(r, "compose run was issued", bool(runs), r["docker"])
    check(r, "…through docker-compose.gpu.yml",
          bool(runs) and "docker-compose.gpu.yml" in runs[0], runs[:1])
    check(r, "…and not through prod-gpu.yml",
          bool(runs) and "prod-gpu" not in runs[0], runs[:1])

    # 11 — "Skip" on a configured cu118 install keeps building from source
    # rather than silently switching to the pre-built cu124 image.
    seed = open(os.path.join(REPO, ".env.example")).read()
    seed = seed.replace("HAYSTACK_DEVICE=cpu", "HAYSTACK_DEVICE=gpu")
    seed = seed.replace("HAYSTACK_CUDA_VERSION=cu124", "HAYSTACK_CUDA_VERSION=cu118")
    r = run("skip keeps an existing cu118 install on the source build",
            cuda="13.0", gpu_choice="4", seed_env=seed)
    show(r)
    log = strip(r["log"])
    check(r, "HAYSTACK_CUDA_VERSION still cu118",
          env_get(r["env"], "HAYSTACK_CUDA_VERSION") == "cu118",
          str(env_get(r["env"], "HAYSTACK_CUDA_VERSION")))
    check(r, "review line names the wheel tag", "gpu (NVIDIA, cu118)" in log)
    check(r, "printed command builds from source",
          "docker-compose.gpu.yml" in log and "--build" in log
          and "docker-compose.prod-gpu.yml" not in log)

    print()
    if FAILED:
        print(f"{len(FAILED)} check(s) FAILED:")
        for f in FAILED:
            print("  - " + f)
        sys.exit(1)
    print("all checks passed")


if __name__ == "__main__":
    main()
