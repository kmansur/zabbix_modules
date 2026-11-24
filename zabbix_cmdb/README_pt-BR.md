# Módulo Zabbix CMDB

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend do Zabbix que oferece funções de banco de dados de gerenciamento de configuração (CMDB), centralizando a consulta e o gerenciamento das informações dos hosts. Ele adiciona o menu CMDB à seção Inventário do Zabbix Web, com pesquisa de hosts e filtro por grupo.

![1](images/1.jpg)
![2](images/2.png)
![3](images/3.jpg)

## Recursos

- **Pesquisa de hosts**: por nome do host ou endereço IP
- **Filtro por grupo**: por grupos de hosts
- **Informações exibidas de cada host**:
  - Nome do host (link para os detalhes do host)
  - Endereço IP
  - Tipo de interface (Agent, SNMP, IPMI, JMX)
  - Total de CPU
  - Memória total
  - Versão do kernel
  - Grupos de hosts
  - Status do host (ativo/desativado)
- **Gerenciamento de grupos de hosts**: estatísticas de todos os grupos de hosts
- **Pesquisa de grupos**: por nome do grupo
- **Estatísticas por grupo**: quantidade de hosts, total de CPU e memória total de cada grupo
- **Internacionalização**: interface em chinês, inglês, russo e português do Brasil
- **Design responsivo**: adapta-se a diferentes tamanhos de tela
- **Interface moderna**: cores em degradê e animações
- **Estatísticas**: total de hosts, total de grupos e quantidade de hosts ativos

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o módulo "Zabbix CMDB" e ative-o.
4. Atualize a página. O módulo aparece no menu **Inventário** como o submenu "CMDB", com os subitens "Lista de hosts" e "Grupos de hosts".

## Observações

- **Desempenho**: em ambientes grandes, considere limitar a quantidade de resultados das consultas.
- **Precisão dos dados**: as informações exibidas refletem o estado atual do banco de dados do Zabbix.
- **Dependência de itens**: a exibição de CPU e memória depende da configuração dos itens correspondentes.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Estrutura de arquivos:

- `manifest.json`: configuração do módulo
- `Module.php`: registro do menu
- `actions/Cmdb.php`: lógica da lista de hosts
- `actions/CmdbGroups.php`: lógica dos grupos de hosts
- `views/cmdb.php`: view da lista de hosts
- `views/cmdb.groups.php`: view dos grupos de hosts
- `lib/LanguageManager.php`: gerenciamento de idiomas (inclui o dicionário `pt_BR`)
- `lib/ItemFinder.php`: utilitários de busca de itens
- `lib/ViewRenderer.php`: utilitários de renderização das views
- `lib/ZabbixVersion.php`: utilitários de compatibilidade entre versões

Para extensões, consulte a [documentação de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

### Configuração de itens do CMDB

Usuários Super admin podem gerenciar as regras de correspondência de total de CPU, uso de CPU, memória total e uso de memória em Inventário → CMDB → Configuração de itens.

- As regras ficam em `data/item_rules.json`; em cada lista de métrica, vale a primeira regra que corresponder.
- A correspondência pode ser por nome ou chave do item, de forma exata ou por padrão sem diferenciar maiúsculas de minúsculas. O padrão aceita trechos e `*`; colchetes são tratados literalmente.
- Reordene as regras com Mover para cima / Mover para baixo. Se vários itens ativos corresponderem, é escolhido o de menor ID. Se o último valor dele não existir, o histórico é lido em vez de pular para a regra seguinte.
- O total de CPU usa núcleos, a memória total usa bytes e o uso é em percentual. Valores de ocioso/disponível podem usar a conversão `100 − valor`.
- Uma lista vazia desativa a métrica. A lista de hosts, os totais e as estatísticas de grupos usam as mesmas regras; alterações salvas valem a partir da próxima requisição.
- O JSON padrão preserva as regras existentes, inclusive as de informações do sistema. Verifique se os itens de reserva de carga de CPU, memória usada e os itens genéricos de memory.size usam as unidades esperadas nos seus templates.
- O PHP precisa de leitura e escrita em `data` e de leitura no JSON. Os demais diretórios de código podem ficar somente leitura. O salvamento usa substituição atômica, com verificação de Super admin e CSRF. Preserve `data/item_rules.json` nas atualizações.
- Falhas de leitura são informadas; não há nenhum valor de reserva fixo oculto.

Testes das regras: `php zabbix_cmdb/tests/item_rules.php` (grava apenas em um diretório temporário do sistema).

## Licença

Este projeto segue a licença do Zabbix. Para mais detalhes, consulte a [Licença do Zabbix](https://www.zabbix.com/license).
