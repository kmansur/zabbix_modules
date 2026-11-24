# Cross-server import/export test report

Test date: 2026-11-05 (Asia/Shanghai). Module: 1.3.0.

## Environments and results

| Environment | Zabbix | Actual web PHP | Module status |
|---|---|---|---|
| 192.168.3.243 | 7.0.32 | PHP 8.3.36 | Module already present, kept enabled |
| 192.168.3.29 | 7.0.32 | PHP-FPM 8.0.30 | Deployed, registered and enabled in this run |

The target machine has no PHP CLI; no extra software was installed, and its real web/PHP-FPM was used for verification. Before deploying to the target, the backup directory `/tmp/zabbix-transfer-cross-backup.5BbdAD` was kept, and SELinux labels were restored only for the module directory.

| Test set | .243 | .29 | Both directions | Total |
|---|---:|---:|---:|---:|
| Host/item business, pagination, permission and CSRF regression | 60 | 60 | — | 120 |
| Template, select-all, i18n real web regression | 36 | 36 | — | 72 |
| Malicious upload and execution policy regression | 35 | 35 | — | 70 |
| Two-way configuration file migration and extra scenarios | — | — | 135 | 135 |
| **Passed** | **131** | **131** | **135** | **397** |

Totals are computed from the last passing run of each test set, without adding debugging reruns. Two-way steps: create independent objects on .243 and download the files, upload them to .29 and import; then delete only the .243 objects created by this test and import the files exported from .29 back into .243 to verify the rebuild. The main flow uploads the downloaded files verbatim instead of rebuilding the envelope by hand. Downloaded originals are saved byte for byte, with a SHA-256 manifest in the development evidence directory.

## Coverage matrix

| Scenario | What was verified | Result |
|---|---|---|
| JSON/CSV | Host CSV, template JSON and item JSON originals uploaded across machines; repeated CSV/JSON imports and data round trips | Passed |
| Pagination and batches | 55 disabled test hosts, 50+5 pagination, full export, 25+25+5 confirmations | Passed |
| Host content | Groups/subgroups, Unicode/quotes/line breaks, tags, inventory, disabled status, IPv6, SNMPv3 interfaces | Passed |
| Templates | Parent and child templates restored in dependency order, items/dependent items, triggers, graphs, LLD rules/prototypes, dashboards, value maps | Passed |
| Items | Local and inherited items distinguished, target interface/master item IDs rebuilt, SNMP references, preprocessing | Passed |
| Proxy | Mapped by name to the target proxy/proxy group, explicit failure when missing | Passed |
| Name mapping | Host and template technical name mapping, five local items and their dependencies restored | Passed |
| Duplicate strategies | Skip existing on create, explicit update, error on duplicates, skip missing objects in update-only mode | Passed |
| Selective overwrite | Interfaces/tags/inventory kept by default and updated when overwrite is checked; template updates keep extra target objects | Passed |
| Missing dependencies | Host group stop/skip, template stop/skip, error reports for missing value maps/master items/interfaces/proxies | Passed |
| Partial failure | Independent items still succeed when a value map is missing; retry succeeds after adding the value map with the same name | Passed |
| Permissions | Low-privilege users cannot import or export templates or hosts they cannot access | Passed |
| Session and concurrency | Missing CSRF, another login session and wrong page types cannot confirm; concurrent confirmations of the same preview run only once | Passed |
| Upload security | PHP/double extensions/disguised content/archives/HTML/XML/invalid encoding/NUL/oversized files, script and JS approval | Passed |
| i18n | English and Simplified Chinese profile languages of one-time users took effect automatically | Passed |
| File name time zone | Two machines, three file types, Asia/Tokyo and America/Phoenix, checked against the HTTP server clock | Passed |

## Issues handled and findings

1. The target .29 did not have the module yet; the same business code as on .243 was deployed, registered and enabled, and target directory readability and SELinux labels were handled.
2. The two machines run different PHP environments (8.3.36 / 8.0.30); compatibility was verified with real requests. The missing CLI on .29 was not mistaken for the module being unable to run, and no extra PHP packages were installed.
3. Fixed the test tooling: the authorization header of the anonymous version endpoint, comparison of read-only metadata automatically added to tags, saving Windows CSV originals and the time zone reference. These are test tool fixes and are not presented as module defects.
4. This round found no new defect requiring changes to the module's business logic. Previously implemented behaviors were verified: target ID rebuild, protection against duplicate inherited items, overwrite switches, script protection, preview binding and concurrency lock, personal time zone, etc.
5. **Value map prerequisite clarified**: value maps inside templates are migrated with the templates; host-local value map definitions are not created automatically by the host/item pages. Local items that reference them require the target to already have a value map with the same name. The test first verified the missing-map error, then created the test map and verified the import succeeded, without counting the expected dependency error as a successful migration.

## Cleanup and evidence

All tests used random independent `codex-cross-*`, `codex-transfer-*`, `codex-template-*` objects and never imported over existing business objects. The test scripts cleaned up the hosts, templates, groups, proxies/proxy groups and users they created. The module on target .29 was kept enabled as required by the task. Private previews follow the module's 30-minute expiry policy; other users' previews were not deleted in bulk.

Local evidence: `.devtools/cross-exact-final/summary.json`, `download-manifest.json`, the downloaded originals/target snapshots/results of each run; regression logs of both machines are in `.devtools/cross-regressions/`. No password was written to scripts or reports.

Reproduction commands (passwords are prompted interactively):

```powershell
python tests/cross_server.py --source http://192.168.3.243/zabbix/ --target http://192.168.3.29/zabbix/ --artifacts PATH_TO_EVIDENCE
python tests/e2e.py --url http://TARGET/zabbix/
python tests/templates_e2e.py --url http://TARGET/zabbix/
python tests/upload_e2e.py --url http://TARGET/zabbix/
```

## Coverage boundary

These limited tests must not be described as covering every scenario. This round verified configuration migration on Zabbix 7.0.32 with two PHP environments; test hosts were disabled and executable test items were disabled, so real agent/SNMP/HTTP collection, real LLD discovery runs, all monitoring protocols, other Zabbix versions and concurrency at the scale of ten thousand hosts were not verified. Cross-environment ID rebuild, portable relationships, security rejection paths and the main strategies were tested for real.
