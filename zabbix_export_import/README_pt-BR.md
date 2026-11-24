# Zabbix Import/Export

[English](README.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend nativo em **Inventário → Zabbix Import/Export** com exatamente três páginas de negócio: hosts, templates de monitoramento e itens personalizados de host. Ele usa o contexto de API do usuário conectado e não cria tabelas no banco de dados.

### Hosts

![Hosts](images/hosts.jpg)

### Templates

![Templates](images/templates.jpg)

### Itens de monitoramento

![Itens de monitoramento](images/items.jpg)

## Recursos

- Transfere configuração de hosts, templates de monitoramento e itens locais em CSV ou JSON. A página de templates também aceita JSON nativo do Zabbix.
- Filtra por grupo, nome ou origem do item, conforme a página. Alterar uma lista de pesquisa aplica o filtro imediatamente. Selecione a página atual ou todos os resultados filtrados em todas as páginas.
- Pré-visualiza as importações antes de gravar qualquer configuração. Revise as diferenças, escolha a política para duplicados e baixe o relatório de resultados.
- A interface segue o idioma do usuário conectado no Zabbix; inglês e português do Brasil estão incluídos. Os nomes dos arquivos exportados usam o fuso horário do usuário no Zabbix, ou o padrão do sistema quando o perfil o herda.

## Instalação

### Instalar o módulo

Implante este diretório como `<frontend>/modules/zabbix_export_import`, com permissão de leitura para a conta do serviço web.

### ⚠️ Ajustar o manifest.json

Este exemplo usa `/usr/share/zabbix`. Se o seu frontend estiver em `/usr/share/zabbix/ui`, use esse caminho nos dois lugares.

```sh
php /usr/share/zabbix/modules/zabbix_export_import/tools/prepare.php /usr/share/zabbix
```

### Ativar o módulo

Como Super admin, abra **Administração → Geral → Módulos**, clique em **Varrer diretório** e ative o **Zabbix Export Import**.

## Observações

Leituras e exportações usam as permissões de API atuais. Importações exigem perfil Admin e permissão nativa de escrita nos objetos de destino; criar grupos de templates ou aprovar conteúdo executável exige Super admin. Cada gravação resolve novamente os objetos de destino e passa pelas verificações de permissão de escrita da API. As requisições POST mantêm a validação nativa de CSRF/SID.

Os limites são 10 MiB, 10000 registros, profundidade JSON 32 e 256 KiB por campo. Os hosts são consultados em lotes de 100; cada confirmação de importação processa 25 linhas. Exportações grandes demais informam um erro em vez de truncar silenciosamente para uma página. Os dados da exportação são montados com memória limitada; não há exportação em streaming ilimitada.

Campos de senha/token/PSK/chave privada, cabeçalhos sensíveis e chaves de configuração secretas são omitidos e marcados com `_redacted`. Esses registros não podem ser importados até que um administrador forneça com segurança os valores ausentes e remova essa marcação antes de uma nova pré-visualização. Segredos embutidos em scripts arbitrários ou em chaves personalizadas não podem ser reconhecidos com perfeição apenas pelo nome; siga as regras locais de tratamento de dados ao revisar as exportações.

Os arquivos de pré-visualização usam permissão 0600 dentro de um diretório temporário 0700 e bloqueio exclusivo durante a confirmação. Os registros de auditoria contêm usuário, data/hora, tipo, hash do objeto, linha e resultado, sem documentos enviados nem credenciais. Os relatórios de resultado podem conter nomes de objetos e detalhes de erro da API nativa.

Use a página de templates para migrar definições de templates, mapas de valores e regras/protótipos de descoberta antes de restaurar os hosts que dependem deles. Macros de host, chaves privadas TLS e valores secretos são excluídos. Pré-requisitos ausentes são informados. Conflitos de nome visível seguem as regras de unicidade do Zabbix de destino.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

Consulte as [notas de desenvolvimento](DEVELOPMENT.md), a [revisão de segurança](SECURITY.md) e o [relatório de testes entre servidores](CROSS_SERVER_TEST_REPORT.md). Se você tiver o diretório local de testes, execute as verificações de CLI a partir do diretório do módulo:

```sh
find . -name '*.php' -exec php -l {} \;
php tests/serializers.php
php tests/transfer_security.php
```

## Configuração do módulo

### 3. Ativar ou atualizar

Varra o diretório e ative o **Zabbix Import/Export** em Administração → Geral → Módulos. O comando de preparação seleciona a versão 1 do manifest para o 6.0 e a versão 2 para o 7.0 e o 8.0. Para atualizar, substitua os arquivos do módulo e execute novamente o comando de preparação.

O tipo de valor JSON para itens exige o Zabbix 8.0. A importação de templates segue o formato de configuração nativo do destino. O Zabbix 7.0 não consegue importar templates nativos no formato do 8.0.

### Uso

As três páginas usam cartões inspirados no Element Plus, filtros compactos de grupo de hosts/nome e uma barra de exportação acima da tabela. Todos os hosts é o padrão e inclui todos os hosts autorizados, ativos e desativados. Grupos pai incluem os descendentes. Filtros antigos e invisíveis do perfil não são restaurados. Os itens mostram por padrão todas as origens, com filtros de nome/origem; registros herdados e descobertos continuam somente leitura. As configurações de importação abrem em uma gaveta lateral e mostram apenas as opções relevantes para a página atual. Nenhum recurso de Vue ou CDN é carregado. Requisições explícitas de exportação no formato antigo mantêm o suporte aos filtros avançados da API.

As listas mostram 50 linhas por página. A caixa de seleção do cabeçalho seleciona a página atual. As seleções feitas em várias páginas do mesmo filtro ficam guardadas no armazenamento de sessão do navegador. O escopo da exportação diferencia explicitamente as linhas selecionadas de todos os registros filtrados, incluindo todas as páginas. Os templates têm filtros próprios de grupo de templates/nome. Selecionar todos os registros filtrados seleciona todas as páginas, permite exclusões individuais, persiste entre páginas e muda automaticamente o escopo da exportação para os registros selecionados. Limpar seleção remove todas as seleções.

Envie um arquivo CSV ou JSON, revise a pré-visualização e as diferenças e confirme. Enviar e pré-visualizar nunca gravam configuração. As pré-visualizações ficam fora da raiz web, expiram após 30 minutos e são vinculadas ao usuário e à sessão atuais. Cada confirmação processa no máximo 25 linhas; continue até que os lotes restantes terminem. Os resultados identificam cada linha com falha e podem ser baixados em JSON. Chamadas de API já concluídas não são revertidas globalmente em caso de falha parcial.

A estratégia padrão cria os objetos ausentes e ignora os existentes. Atualizar objetos existentes exige uma escolha explícita; no modo somente atualização, objetos ausentes são ignorados. Itens locais de host têm uma estratégia própria para duplicados. Os nomes técnicos de hosts e templates podem ser mapeados com um objeto JSON, por exemplo `{"origem":"destino"}`.

Grupos de hosts ausentes podem interromper a linha, ser ignorados ou ser criados por um Super admin. Templates ausentes podem ser mapeados, ignorados ou interromper a linha; templates substitutos vazios nunca são criados. Vínculos de templates, interfaces, tags e inventário existentes são preservados, a menos que a opção de substituição correspondente seja selecionada.

#### Relacionamentos portáveis

Hosts são associados pelo `host` técnico; itens por `(host de destino, key_)`; templates pelo `host` técnico; grupos, proxies, grupos de proxies e mapas de valores pelos respectivos nomes. IDs inteiros de origem nunca são usados como IDs de destino. Referências de interface são associadas por tipo e endpoint exatos quando possível, e depois pela única interface principal do tipo exigido.

A importação de hosts resolve grupos/templates/proxies/interfaces, cria ou atualiza o host e os vínculos de templates e, opcionalmente, restaura os itens locais. Itens herdados são recriados pela herança de templates do Zabbix. Itens descobertos são apenas referências e precisam ser restaurados pelas regras/protótipos de descoberta originais. Itens dependentes são ordenados topologicamente; ciclos e itens mestres ausentes são erros. Os mapas de valores de destino já precisam existir.

A transferência de templates usa a exportação/importação de configuração nativa do Zabbix e preserva as entidades de monitoramento. Grupos de templates ausentes são rejeitados, a menos que um Super admin escolha explicitamente criá-los. Conflitos de UUID/nome de template e objetos de nível superior não relacionados são rejeitados.

#### Seleção de idioma

A interface usa o idioma do usuário conectado no Zabbix e recorre ao padrão do sistema quando o perfil usa Padrão. Os catálogos de inglês e português do Brasil ficam em `locales` (o português em `locales/pt_BR.php`); catálogos de outros idiomas do Zabbix podem ser adicionados. Textos compartilhados recorrem ao gettext do Zabbix, com inglês como reserva para textos do módulo sem tradução. O idioma do navegador não é usado para sobrepor o perfil.

#### Formato

Este é um formato portável próprio, e não o `configuration.import` do Zabbix. Consulte [schemas/transfer.schema.json](schemas/transfer.schema.json) e os seis [exemplos](examples) com dois registros cada.

O JSON é UTF-8 com indentação legível e este envelope: `schema_version="1.0"`, `record_type=hosts|items|templates`, `source_zabbix_version`, `exported_at` em ISO e uma lista `records`. A entrada aceita BOM UTF-8; a saída não tem BOM.

O CSV tem exatamente cinco colunas: `schema_version,record_type,source_zabbix_version,exported_at,record`. Cada célula `record` é um objeto JSON, incluindo arrays e objetos aninhados. O escape padrão de aspas, vírgulas e quebras de linha do CSV preserva acentos, caracteres chineses, emojis e textos com várias linhas.

A proteção contra fórmulas não perde dados: os valores de negócio ficam dentro de uma célula com objeto JSON que começa com `{`; as demais colunas são metadados controlados. Nenhum apóstrofo é adicionado ou removido. Assim, textos de negócio que parecem fórmulas fazem o ciclo de ida e volta sem virar fórmulas de planilha nas células exportadas.

Campos obrigatórios:

| Tipo | Obrigatórios | Outros dados suportados |
|---|---|---|
| hosts | host, host_groups | name, description, status, interfaces, modo de monitoramento e nomes de proxy, templates, tags, inventory, inventory_mode, item_references |
| items | host, name, key_, type, value_type | status, delay/history/trends, units, description, interface, master_item, valuemap, tags, preprocessing e parâmetros de API específicos do tipo |
| templates | template, configuration | name; configuration contém um template nativo |

As origens de itens são `local`, `template` e `discovery`. Referências de template e de descoberta contêm os nomes/chaves de origem. Os campos opcionais `source_hostid`, `source_itemid` e `_debug` são apenas para diagnóstico. Campos e tipos não suportados são informados, em vez de serem aceitos silenciosamente como migrações bem-sucedidas.

## Licença

Consulte os [termos de licença](../README_pt-BR.md#licença) do repositório. Preserve a atribuição a terceiros ao redistribuir.
