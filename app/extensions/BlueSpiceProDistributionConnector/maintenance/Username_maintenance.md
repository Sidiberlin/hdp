# Normalization
Several authentication plugins (LDAP, SAML, ...) normalize the username to all lowercase characters. When such a plugin gets enabled on a legacy database it may be required to apply this normalization to the existing usernames. Extension:RenameUser, which is part of MediaWiki provides a maintenance script for this purpose.

First we need to create a list of all usernames that need to be normalized. This can be done with the following script:

```bash
php extensions/BlueSpiceProDistributionConnector/maintenance/CheckUserDuplicates.php > /tmp/duplicates.txt
```

This script will output a list of all usernames that are not normalized. The output will look like this:

```
SomeUser	Someuser
SomeUser2	Someuser2
SomeUser3	Someuser3
...
```

One can then create calls to the maintenance script for each username:

```bash
php extensions/BlueSpiceProDistributionConnector/maintenance/FormatRenameUserCalls.php --src /tmp/duplicates.txt > /tmp/renameuser.txt
```

The output will look like this:

```bash
php extensions/Renameuser/maintenance/renameUser.php --old "SomeUser" --new "Someuser"
php extensions/Renameuser/maintenance/renameUser.php --old "SomeUser2" --new "Someuser2"
php extensions/Renameuser/maintenance/renameUser.php --old "SomeUser3" --new "Someuser3"
...
```

**ATTENTION! Extension:RenameUser can only be used if the normalized users do not exist yet! Otherwise Extension:UserMerge needs to be used**

## Merge duplicates
If there are duplicate users in the database, they need to be merged. This can be done with the following script:

```bash
php extensions/BlueSpiceProDistributionConnector/maintenance/MergeUserBatch.php --src /tmp/duplicates.txt
```

## Add normalized users
In some cases one might want to add normalized users to the database. This can be done with the following script:

```bash
php extensions/BlueSpiceProDistributionConnector/maintenance/FormatCreateAndPromoteCalls.php --src /tmp/duplicates.txt --defaultPassword "somePassword" > /tmp/createuser.txt
```

It will output commands like this:

```bash
php maintenance/createAndPromote.php --username "Someuser" "somePassword"
php maintenance/createAndPromote.php --username "Someuser2" "somePassword"
php maintenance/createAndPromote.php --username "Someuser3" "somePassword"
...
```