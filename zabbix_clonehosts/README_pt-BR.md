# Módulo Zabbix Clonehosts

[English](README.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

O Zabbix Clonehosts é um módulo de frontend do Zabbix que clona e importa em lote uma grande quantidade de hosts com base na configuração de um host já monitorado. Ele adiciona o item "Importação de hosts em lote" ao menu "Coleta de dados" do Zabbix Web (logo após "Hosts"), com importação por arquivo CSV e preenchimento em tabela online, pré-visualização, detecção de conflitos e acompanhamento do progresso da importação em tempo real.

![1](images/image.png)
![2](images/image-1.png)
![3](images/image-2.png)
![4](images/image-3.png)

## Recursos

- **Clonagem a partir de um host de origem**: escolha qualquer host monitorado como modelo de clonagem. Toda a configuração dele (interfaces, grupos, templates, tags, macros, TLS, IPMI, modo de inventário etc.) pode ser herdada.
- **Duas formas de entrada de dados**:
  - Upload de CSV: suporta UTF-8 e GBK, com detecção automática do cabeçalho e da codificação, e modelo CSV para download.
  - Preenchimento em tabela online: adicionar/remover linhas, limpar tudo, validação em tempo real.
- **Herança inteligente de campos**: apenas o nome do host e o IP da interface são obrigatórios. Os demais campos (nome visível, porta, grupos de hosts, templates, tags, macros, descrição) usam a configuração do host de origem quando deixados em branco.
- **Criação automática de grupos de hosts**: grupos de hosts informados no CSV que ainda não existem são criados pela API antes de serem vinculados.
- **Pré-visualização e detecção de conflitos**: pré-visualização completa antes da importação; detecta automaticamente conflitos de nome de host, campos obrigatórios ausentes, duplicados no lote e colisões de nome com hosts/templates existentes. Rótulos de status distinguem "existe / vínculo direto", "será criado", "não encontrado" e "herdado da origem".
- **Importação seletiva**: na pré-visualização você marca ou desmarca os hosts que deseja importar. Hosts em conflito são ignorados automaticamente; hosts prontos também podem ser excluídos individualmente.
- **Voltar para edição**: da pré-visualização é possível voltar à tabela online com todos os dados preservados, para corrigir os hosts que não estão prontos sem perder o que já foi digitado.
- **Acompanhamento da importação**: os hosts são criados um a um via AJAX, com barra de progresso em tempo real e contadores de sucesso/falha.
- **Exportação do relatório de resultados**: ao final da importação, baixe um relatório CSV (nome do host, IP, ID do host, resultado, mensagem de erro).
- **Idiomas**: a interface segue o idioma do usuário ou do sistema no Zabbix (inglês e português do Brasil), sem depender de gettext.
- **Design responsivo**: adapta-se a diferentes tamanhos de tela.
- **Interface moderna**: segue a linguagem visual nativa do Zabbix.

## Instalação

### Instalar o módulo

```bash
# Para Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Para Zabbix 7.2+ / 7.4 / 8.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Ajustar o manifest.json

Se você usa o Zabbix 6.0, altere o `manifest_version` em `zabbix_clonehosts/manifest.json` para `1.0`:

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_clonehosts/manifest.json
```

### Ativar o módulo

1. Acesse Administração → Geral → Módulos.
2. Clique em **Varrer diretório** para detectar os novos módulos.
3. Localize e ative o módulo "Zabbix Clonehosts".
4. Atualize a página — o módulo aparece em **Coleta de dados → Importação de hosts em lote**, logo após "Hosts".

## Observações

- **Desempenho**: a importação é sequencial e lotes grandes podem demorar. Limite recomendado: cerca de 500 hosts por execução.
- **Unicidade do nome do host**: no Zabbix, nomes de hosts compartilham o mesmo espaço de nomes dos templates e precisam ser únicos globalmente. A pré-visualização verifica conflitos com hosts e com templates.
- **Dependência de templates**: os templates citados no CSV precisam existir no Zabbix. Templates ausentes são marcados como "não encontrado" e ignorados na importação.
- **Codificação do CSV**: recomenda-se UTF-8. Se caracteres especiais aparecerem corrompidos, mude a codificação da página para GBK e interprete o arquivo novamente.
- **Permissões**: o módulo exige perfil Admin ou superior no Zabbix.
- **Precisão dos dados**: os hosts criados se baseiam em um snapshot da configuração atual do host de origem. Alterações no host de origem durante a importação não afetam os hosts já criados.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Arquivos principais:

- `manifest.json` — configuração do módulo, rotas e declaração de arquivos
- `Module.php` — registro do menu (compatível com `Zabbix\Core\CModule` e `Core\CModule`)
- `CompatHelper.php` — compatibilidade com a API do Zabbix 6.0/6.4/7.x/8.x
- `LangHelper.php` — textos em inglês e português do Brasil (array PHP puro, sem gettext)
- `actions/Clonehosts.php` — controlador da página principal (seleção do host de origem, upload de CSV, tabela)
- `actions/ClonehostsSource.php` — endpoint AJAX que carrega a configuração do host de origem
- `actions/ClonehostsPreview.php` — controlador da pré-visualização (detecção de conflitos, status dos campos)
- `actions/ClonehostsImport.php` — endpoint AJAX de importação (cria os hosts um a um)
- `views/` — views das páginas (principal, pré-visualização, resposta JSON)
- `assets/js/` — JavaScript (interpretação do CSV, gerenciamento da tabela, progresso da importação via AJAX)
- `assets/css/` — estilos do módulo
- `clonehosts_template.csv` — modelo de importação CSV

Para estender o módulo, consulte a documentação de desenvolvimento de módulos do Zabbix: https://www.zabbix.com/documentation/current/en/devel/modules/file_structure

## Configuração do módulo

### Formato do CSV

O módulo inclui o modelo `clonehosts_template.csv`, que pode ser baixado na página de importação pelo botão "Baixar modelo CSV". O cabeçalho do arquivo de modelo está em inglês; o modelo baixado pela interface em português usa cabeçalhos em português (por exemplo `Nome do host(*)`), e os dois são reconhecidos. As colunas são lidas pela posição. Descrição das colunas, na ordem:

| Campo (cabeçalho do modelo) | Obrigatório | Descrição |
| --- | --- | --- |
| Host Name(*) — Nome do host | Sim | Identificador do host; deve ser único globalmente (compartilha o espaço de nomes com os templates) |
| Visible Name — Nome visível | Não | Se vazio, usa o nome do host |
| Interface IP(*) — IP da interface | Sim | Endereço IP da interface do host |
| Port — Porta | Não | Se vazia, usa a porta do host de origem |
| Host Groups — Grupos de hosts | Não | Vários grupos separados por `;`; criados automaticamente se não existirem |
| Templates — Templates | Não | Vários templates separados por `;`; associados pelo nome técnico ou visível |
| Tags — Tags | Não | Formato `tag=valor`, vários pares separados por `;` |
| Macros — Macros | Não | Formato `{$MACRO}=valor`, vários pares separados por `;` |
| Description — Descrição | Não | Descrição do host |

Exemplo:

```csv
Host Name(*),Visible Name,Interface IP(*),Port,Host Groups,Templates,Tags,Macros,Description
web-server-01,Web Server 01,192.168.1.10,10050,Servers;Web Servers,Linux by Zabbix Agent;Nginx by HTTP,env=prod;os=linux,{$SNMP_COMMUNITY}=public,Servidor web 01
db-server-01,DB Server 01,192.168.1.20,10050,Servers;Database Servers,MySQL by Zabbix Agent,env=prod;os=linux;role=db,{$MYSQL_PORT}=3306,Servidor de banco de dados 01
```

## Licença

Este projeto é licenciado sob a GPL-2.0.
