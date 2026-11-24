# Módulo Zabbix Database Export

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

A versão atual é a **3.0.4**, instalada em `zabbix_database_export`.

Este módulo de frontend exporta backups do banco de dados a partir do Zabbix Web. Ele reaproveita a conexão de banco do frontend para exportar as definições das tabelas base e suas linhas em um arquivo SQL. Acesse **Administração → Exportação do banco de dados** como Super admin do Zabbix.

## Recursos

- **Detecção do banco de dados**. Escolhe automaticamente a implementação de exportação para MySQL/MariaDB ou PostgreSQL e exibe o tipo e a versão do banco.
- **Seleção de tabelas**. Mostra nomes das tabelas, engines de armazenamento e tamanhos estimados incluindo dados e índices. Filtre por nome e selecione tabelas em lote.
- **Modos de exportação**. Exporte estrutura e dados, somente estrutura ou somente dados. Tabelas não selecionadas sempre mantêm suas instruções `CREATE TABLE`, com colunas, índices e restrições.
- **Destino da saída**. Baixe no navegador ou salve no servidor. Com o download pelo navegador, arquivos de até 500 MB (500.000.000 bytes) são baixados diretamente; arquivos maiores são salvos automaticamente no diretório informado.
- **Fuso horário e idioma**. Os nomes de arquivo incluem o tipo de banco e usam o fuso horário do usuário atual. A interface segue o idioma do frontend e está disponível em inglês, chinês, russo e português do Brasil.
- **Interface**. CSS e JavaScript locais oferecem um layout responsivo inspirado no Element Plus.

![1](images/1.png)

## Instalação

### Instalar o módulo

Copie `manifest.json`, `Module.php`, `actions/`, `assets/`, `lib/` e `views/` deste diretório para `modules/zabbix_database_export/` no frontend do Zabbix. Os caminhos mais comuns dos pacotes são:

| Versão do Zabbix | Diretório do módulo |
| --- | --- |
| 7.0 | `/usr/share/zabbix/modules/zabbix_database_export/` |
| 8.0 | `/usr/share/zabbix/ui/modules/zabbix_database_export/` |

### ⚠️ Ajustar o manifest.json

No Zabbix 6.0, altere a versão do manifest do módulo para 1.0. No 7.0/8.0, mantenha 2.0.

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' /usr/share/zabbix/modules/zabbix_database_export/manifest.json
```

### Ativar o módulo

1. Entre como Super admin e abra **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** e ative o **Zabbix Database Export**.
3. Atualize a página e abra **Administração → Exportação do banco de dados**.

## Observações

- **Conteúdo do backup**. Um backup completo inclui usuários e credenciais. O SQL de MySQL/MariaDB usa `CREATE TABLE` e `INSERT` com nomes de colunas explícitos. O PostgreSQL usa SQL nativo do `pg_dump` e dados em `COPY`, incluindo índices, restrições e sequências. Ao importar um arquivo gerado no modo **Somente dados**, as tabelas selecionadas precisam já ter definições compatíveis, e as tabelas não selecionadas precisam poder ser criadas no banco de destino.
- **Consistência dos dados**. Os dados InnoDB são lidos de um único snapshot de transação. No PostgreSQL, as exportações de estrutura e de dados compartilham um único snapshot de transação.
- **Requisitos do PostgreSQL**. Ative o `pgsql` e o `proc_open` no PHP e instale um `pg_dump` compatível no servidor do frontend. O módulo reaproveita as configurações de conexão e TLS do frontend. O arquivo de senha temporário tem modo `0600` e é removido em seguida.
- **Restauração do SQL**. Importe arquivos do PostgreSQL com `psql -v ON_ERROR_STOP=1 -d banco_de_destino -f backup.sql`. Os dumps usam o dialeto do banco de origem e exigem tipos e extensões compatíveis no banco de destino.
- **Arquivos grandes**. O módulo primeiro grava o backup completo no diretório temporário do PHP. Salvar no servidor também exige espaço livre suficiente no destino. Timeouts do servidor web, do proxy e do PHP-FPM podem afetar exportações demoradas.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

- `manifest.json`, `Module.php` — registro do módulo e entrada de menu
- `actions/DatabaseExport.php`, `views/databaseexport.php` — tratamento das requisições e renderização
- `lib/SqlExport.php`, `lib/PostgresqlExport.php`, `lib/BackupFile.php` — exportação SQL e gravação dos arquivos
- `lib/LanguageManager.php`, `assets/` — traduções (inclui `pt_BR`) e arquivos do frontend
- `tests/checks.php` — verificações de regressão da exportação SQL e do limite de 500 MB

As verificações de regressão do PostgreSQL em `tests/postgresql.php` cobrem seleção de tabelas, exclusão de histórico, sequências, dados binários, JSON e colunas geradas.

## Configuração do módulo

### Uso

1. Em **Escopo das tabelas**, escolha todas as tabelas ou as tabelas selecionadas. Use o campo de pesquisa para reduzir a lista e a caixa de seleção do cabeçalho para selecionar todas as tabelas filtradas.
   Marque **Excluir registros de histórico** para exportar apenas a estrutura de todas as tabelas cujo nome começa com `history`.
2. Escolha o **Conteúdo** da exportação.

   | Modo | Tabelas selecionadas | Tabelas não selecionadas |
   | --- | --- | --- |
   | Estrutura e dados | Estrutura e linhas | Estrutura |
   | Somente estrutura | Estrutura | Estrutura |
   | Somente dados | Linhas | Estrutura |

3. Escolha **Baixar no navegador** ou **Salvar no servidor**, informe o diretório no servidor e clique em **Exportar SQL**. Ao salvar no servidor, a página mostra o caminho absoluto e o tamanho do arquivo.

#### Nomes dos arquivos exportados

Downloads pelo navegador, salvamentos manuais no servidor e salvamentos automáticos acima de 500 MB usam os formatos abaixo. O carimbo de data/hora segue o fuso horário do usuário atual do Zabbix.

| Tipo de banco | Formato do nome do arquivo |
| --- | --- |
| MySQL | `zabbix_database_backup_mysql_yyyy-mm-dd_hh-mm-ss.sql` |
| MariaDB | `zabbix_database_backup_mariadb_yyyy-mm-dd_hh-mm-ss.sql` |
| PostgreSQL | `zabbix_database_backup_postgresql_yyyy-mm-dd_hh-mm-ss.sql` |

#### Configurar o diretório de backup no servidor

O diretório padrão é `/tmp/zabbix`, e o módulo tenta criá-lo se necessário. Para permitir outros locais, adicione um `config.local.php` ao diretório do módulo implantado:

```php
<?php
return ['/var/lib/zabbix/database-backups'];
```

No formulário, informe um diretório configurado ou um de seus subdiretórios existentes. O diretório deve ficar fora da raiz web, pertencer à conta do serviço web e ter modo `0700`. Os arquivos exportados têm modo `0600`, e arquivos existentes com o mesmo nome são preservados. Em implantações com contêineres, o diretório fica dentro do contêiner do frontend e pode ser mapeado para um volume persistente.

## Licença

Consulte os [termos de licença](../README_pt-BR.md#licença) do repositório. Preserve a atribuição a terceiros ao redistribuir.
