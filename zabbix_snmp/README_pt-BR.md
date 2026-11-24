# Módulo Zabbix SNMP

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend do Zabbix que oferece um assistente SNMP. Ele adiciona o menu **Assistente SNMP** à seção **Coleta de dados** do Zabbix Web, com duas subpáginas: **Navegador de MIBs** e **SNMP Walk**. Ajuda os operadores a navegar por arquivos MIB, executar SNMP walks, resolver OIDs e criar itens de monitoramento ou gerar templates SNMP com um clique.

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## Recursos

### Navegador de MIBs (MIBs do Zabbix)

- **Varredura automática dos diretórios de MIBs**: detecta os diretórios de MIBs SNMP comuns em Linux / Unix / Windows e prioriza os diretórios da variável de ambiente `MIBDIRS`
- **Listas de diretórios e arquivos**: selecione um diretório e um arquivo MIB nas listas suspensas
- **Tabela de objetos**: interpreta os objetos de um arquivo MIB e mostra OID, OID resolvido, sintaxe/acesso/status, descrição etc.
- **Ver código-fonte**: mostra o conteúdo bruto de um arquivo MIB com um clique
- **Tela cheia**: a lista de objetos pode ser aberta em um diálogo em tela cheia
- **Copiar e testar**: copie o comando snmpget ou execute um teste no host selecionado

### SNMP Walk (Walk do Zabbix)

- **Seleção de grupo de hosts + host**: lê automaticamente a interface SNMP do host e os parâmetros de conexão (v1 / v2c / v3, com resolução de macros)
- **Resultados em tabela**: interpreta a saída do snmpwalk em uma tabela com índice, OID, OID resolvido, arquivo MIB, módulo, tipo de dado e valor
- **Ver dados brutos**: mostra a saída original do snmpwalk em uma janela
- **Copiar comando / copiar OID**: copia o comando snmpget ou o OID numérico com um clique
- **Paginação no navegador**: exibe grandes volumes de resultados página por página (50/100/200 por página) para manter a página ágil
- **Criar item**: selecione um resultado para criar um item SNMP no host (mapeamento automático do tipo de valor e eliminação de chaves duplicadas)
- **Criar template em lote**: marque vários resultados, informe o nome e o grupo do template e crie um template SNMP com os itens em um clique

### Geral

- **Internacionalização**: interface em chinês, inglês, russo e português do Brasil
- **Interface moderna**: tabelas e interações limpas
- **Controle de permissões**: criar itens / templates exige privilégios de administrador no Zabbix

## Instalação

### Instalar o módulo

```bash
# Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.2+ / 7.4 / 8.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Ajustar o manifest.json

```bash
# ⚠️ No Zabbix 6.0, altere o manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_snmp/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o módulo "Zabbix SNMP" e ative-o.
4. Atualize a página. O módulo aparece no menu **Coleta de dados** como o submenu "Assistente SNMP", com os subitens "MIBs do Zabbix" e "Walk do Zabbix".

## Observações

- **Comandos necessários**: o SNMP Walk depende das ferramentas do `net-snmp` (`snmpwalk` / `snmpget` / `snmptranslate`) instaladas no servidor web do Zabbix.
- **Permissões de diretório**: se o usuário web do Zabbix não puder ler um diretório, a página ainda lista o diretório, mas não consegue carregar o conteúdo dos arquivos.
- **Limitação no nome do template**: o nome técnico de templates no Zabbix só aceita letras sem acento, números, pontos (.), sublinhados (_) e hifens (-); caracteres acentuados ou chineses não são suportados. O nome do grupo de templates pode usar acentos.
- **Desempenho**: para resultados de walk muito grandes, a tabela usa paginação no navegador para manter a página ágil.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Estrutura de arquivos:

- `manifest.json`: configuração do módulo
- `Module.php`: registro do menu
- `actions/Snmp.php`: lógica do navegador de MIBs
- `actions/SnmpWalk.php`: lógica do SNMP Walk
- `actions/SnmpSource.php`: leitura do código-fonte das MIBs
- `actions/SnmpItemCreate.php`: criação de um item
- `actions/SnmpTemplateCreate.php`: criação de template com itens em lote
- `views/snmp.php`: view do navegador de MIBs
- `views/snmp.walk.php`: view do SNMP Walk
- `lib/MibRepository.php`: interpretação de MIBs, execução de walks e tratamento de OIDs
- `lib/LanguageManager.php`: gerenciamento de idiomas (inclui o dicionário `pt_BR`)
- `lib/ViewRenderer.php`: utilitários de renderização das views
- `lib/ZabbixVersion.php`: utilitários de compatibilidade entre versões

Para extensões, consulte a [documentação de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

### Diretórios padrão

- `/usr/share/snmp/mibs`
- `/usr/local/share/snmp/mibs`
- `/usr/share/mibs`
- `/usr/local/share/mibs`
- `/var/lib/mibs`
- `/usr/share/net-snmp/mibs`
- `/opt/share/snmp/mibs`
- `/opt/local/share/snmp/mibs`
- `C:\usr\share\snmp\mibs`
- `C:\usr\local\share\snmp\mibs`
- `C:\net-snmp\share\snmp\mibs`
- `C:\Program Files\Net-SNMP\share\snmp\mibs`
- `C:\Program Files (x86)\Net-SNMP\share\snmp\mibs`

## Licença

Este projeto segue a licença do Zabbix. Para mais detalhes, consulte a [Licença do Zabbix](https://www.zabbix.com/license).
