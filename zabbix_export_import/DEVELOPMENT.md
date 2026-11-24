# Development and delivery notes

The implementation directory is `zabbix_export_import`, as specified by the user. The unique ID is `zabbix-import-export` and the namespace is `Modules\ZabbixImportExport`.

Real environment: Zabbix 7.0.32 / PHP 8.3.36. Installed and enabled at `/usr/share/zabbix/modules/zabbix_export_import`; the existing CMDB module was not modified.

See [README.md](README.md) and [README_pt-BR.md](README_pt-BR.md) for features, installation and usage. Format definitions and the six examples are in `schemas/` and `examples/`. Test and real verification scope: [tests/integration.md](tests/integration.md).

Target source code reviewed: CModule, CController, CModuleManager, the CModule API service, CHost, CItem/CItemGeneral, CControllerHostList, configuration.host.list.php and its JavaScript, CTagFilterFieldHelper, hostgroups.inc.php. Host group filtering handles the shared mutable API wrapper of the Zabbix frontend specifically: child groups must be resolved before selecting the Host service.

Official references:
- https://www.zabbix.com/documentation/7.4/en/devel/modules
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/host/get
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/item/object
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/module/create
- https://raw.githubusercontent.com/zabbix/zabbix/release/6.0/ui/include/classes/core/CModule.php
- https://raw.githubusercontent.com/zabbix/zabbix/release/6.0/ui/include/classes/core/CModuleManager.php
- https://raw.githubusercontent.com/zabbix/zabbix/release/6.0/ui/include/classes/mvc/CController.php

Version boundary: real runtime verification was done on 7.0.32; standalone 6.0/7.4 environments have not been accepted yet, and 8.0 compatibility is not claimed. Module registration migration always requires deploying the code files first.

1.1.0: completed the import security review and the CMDB-style compatibility layer refactoring; see [SECURITY.md](SECURITY.md). CMDB is not modified; executable configuration is blocked by default and requires explicit Super admin approval.

1.2.0: following the user's latest request, removed the large native filter forms from the three pages, with all authorized hosts by default; isolated styles with the Element Plus palette, export at the top of the table, an import drawer, responsive layout and focus management. Items default to all origins, and old implicit profile conditions no longer take part in page queries.

1.3.0: following the user's correction, front-end module registration migration was replaced by template migration. Templates use the native configuration export/importcompare/import; template groups, dependency topology, UUID identity checks, rejection of smuggled top-level objects and approval of executable configuration; select-all became all + exclusions, with the authorized set always recomputed by the server; i18n uses the user language/system default with separate message catalogs.

1.3.1: exports use an independent data copy and never rewrite list records or remove `_selection` early; pagination still works after exceptions. Download headers are sent only after serialization and time zone computation succeed. The template list does not read the unused large description text. The unified file limit is 10 MiB, the preview creation limit 32 MiB and the state limit 64 MiB; the global quota is unchanged.
