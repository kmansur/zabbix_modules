# Import security review (1.1.0)

## Conclusions and boundaries

Import files are only parsed as restricted CSV/JSON data. Uploaded content is never executed; `eval`, `unserialize`, shell commands and decompression tools are not used, and nothing is written to the web directory under the uploaded file name. Front-end modules only register **pre-deployed code**; PHP/JS files cannot be installed through data files.

Two kinds of execution must be distinguished: uploaded content is never executed as PHP by this module, but scripts, commands, HTTP requests, JavaScript preprocessing and similar items in a valid Zabbix configuration may be executed by Zabbix once the items are created or enabled. That is why 1.1.0 blocks executable configuration by default; "the JSON parses" must not be mistaken for "the configuration is safe".

## Hardening

- New `UploadValidator` that checks the actual PHP upload temporary file, extension, file name, real size, `fileinfo` content type, UTF-8, data container and schema. It rejects PHP/PHAR/PHTML, executables, archives, double extensions, disguised non-text content and oversized data. The client-declared MIME type is never trusted.
- Only temporary files for which `is_uploaded_file()` is true are read; arbitrary server paths are not accepted; the real file size and bounded stream reads are used. Valid quoted CSV headers are kept.
- External checks, database, SSH/Telnet, HTTP, Script/Browser, `system.run` and JavaScript preprocessing are blocked by default. Updates also check the preprocessing kept on the target, so ignored code cannot be activated through a status update.
- Executable configuration can only be approved by a **Super admin** during preview. The approval is bound to the previewed data; permissions and configuration are checked again on confirmation. The preview shows code differences and risk warnings. The approval does not mean the code was judged harmless and should only be given to trusted sources.
- PHP tags and common code/command-related keys in module configuration are also blocked by default; the semantics of ordinary configuration still depend on the deployed extension and must be reviewed item by item, instead of relying on generic keyword checks in place of the extension's own security.
- When restoring host-local items, the item's `host` must match the host record that contains it, so a host file cannot smuggle writes to other hosts; the preview includes the local items to be restored.
- Module directories and manifests are restricted to canonical paths; directory/manifest symlinks, path traversal and oversized manifests are rejected.
- Preview storage must be outside Zabbix and the known web roots, with directories 0700 and files 0600, owner and symlink checks, bound to the user/session; native CSRF/SID always protects POST.
- Preview JSON uses `JSON_HEX_TAG`: business values are kept, but `<` and `>` are stored as Unicode escapes on disk, so raw PHP/HTML tags are never kept. Display still uses native HTML escaping.
- At most 5 previews per session are kept, evicting older ones; created data is limited to 16 MiB and persistent state to 32 MiB; globally at most 100 previews and 128 MiB, with a quota lock to reduce concurrent bypasses, and expired data is cleaned up.

## Verification

```sh
php tests/uploads.php
php tests/storage.php
for v in 6.0 7.0 7.4 8.0 fallback6 fallback7 unknown; do php tests/compatibility.php "$v"; done
python tests/upload_e2e.py --url http://YOUR-ZABBIX/zabbix/ --username Admin
```

- Upload/execution policy unit checks: 51 passed.
- Preview storage/module path checks: 15 passed.
- Version and interface branch checks: 87 passed; these are isolated simulated-interface tests and do not represent real acceptance on other versions.
- Existing security/filter checks: 50 passed; format checks: 26 passed.
- On the target environment Zabbix 7.0.32 / PHP 8.3.36, 36 real malicious upload/execution policy tests and 58 business regression tests passed.
- All 26 PHP files passed the syntax check.

Real tests uploaded PHP, renamed PHP, double extensions, PHP/PHTML double extensions, ZIP/path traversal archives, XML, serialized PHP strings, HTML, NUL and oversized files, and showed that no code execution marker was produced. PHP-like descriptive text inside valid JSON stays data and is displayed escaped. The approval path only created one **disabled** script item without side effects, which was deleted afterwards. No untrusted uploaded script was executed on the server or an agent.

No standalone real Zabbix 6.0 / 7.4 environment was available this time. The compatibility layer uses exact version constants, CMDB-style native class detection and interface capability detection; it keeps the 6.0 branch (manifest 1/SID/old module path) and the 7.x branch (manifest 2/CSRF/new module manager). Unknown environments are rejected explicitly, without an empty module base class or forged tokens. The 8.0 branch test only verifies version classification and does not claim runtime compatibility with 8.0.

## 1.3.0 template path

The former module-registration route is removed from the manifest and navigation. Template uploads accept bounded JSON/CSV data, including native Zabbix JSON, and never upload PHP/module files. Each record is restricted to one named template; unrelated top-level hosts/maps/media/images and nested numeric source IDs are rejected. Native import comparison is read-only. Confirmation repeats policy, identity and write-permission checks before native configuration import. Native template UUIDs may not resolve to another technical name. Scripts, commands, active requests, JavaScript preprocessing and Web scenarios require explicit Super admin approval. Native secret macros are redacted, and preview differences are redacted again. Template updates never enable `deleteMissing`.

All-filtered selection is represented as a mode plus exclusions. Export recomputes the current user's filtered API result before applying exclusions; client selection cannot expand permissions. The module reads locale only from Zabbix's user/default settings, validates locale identifiers before loading a local catalog and escapes translated text through native HTML components.
