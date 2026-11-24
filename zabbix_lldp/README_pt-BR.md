# Módulo Zabbix LLDP Topology

[English](README.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend do Zabbix que exibe a topologia de vizinhança LLDP. Ele lê os hosts vinculados ao template **Lldp Discovery** e os valores mais recentes dos itens `lldp.remote.info[*]`, e desenha os dispositivos locais e remotos como círculos SVG ligados por linhas. Passe o mouse para ver os detalhes das portas.

O módulo adiciona **Topologia LLDP** ao menu **Monitoramento** do Zabbix Web.

## Recursos

- **Filtro por template**: apenas os hosts vinculados ao template LLDP e seus grupos aparecem nas listas
- **Filtros de grupo / host**: todos os grupos, todos os hosts ou um escopo específico
- **Consulta sob demanda**: a topologia não é carregada ao abrir a página; clique em **Consultar** para buscar os dados
- **Visualização da topologia**: dispositivos locais à esquerda, vizinhos remotos à direita, com círculos e linhas
- **Detalhes ao passar o mouse**: nomes dos dispositivos local e remoto, portas e hora da última atualização
- **Estatísticas**: quantidade de hosts, enlaces e dispositivos, além da quantidade de itens ignorados
- **Extensível**: o JSON dos nós inclui um campo `image` reservado para futuros ícones de dispositivo
- **Idiomas**: inglês e português do Brasil

## Instalação

### Instalar o módulo

```bash
# Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Ajustar o manifest.json (somente Zabbix 6.0)

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_lldp/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório**.
3. Ative o **Zabbix LLDP Topology**.
4. Atualize a interface; **Topologia LLDP** aparece em **Monitoramento**.

## Observações

- Se o template não for encontrado, verifique `template_name` em `data/config.json`.
- Itens com `lastvalue` vazio ou com valor fora do formato `DISPOSITIVO(PORTA)` são ignorados e contados nas estatísticas.
- Os nomes dos dispositivos remotos vêm do LLDP e podem diferir dos nomes dos hosts no Zabbix; nomes idênticos são mesclados em um único nó.
- Fora do escopo da v1: ícones de dispositivo, layout por forças, mesclagem de enlaces bidirecionais e `REMOTE_SYS_DESC` nas dicas.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

```
zabbix_lldp/
├── manifest.json
├── Module.php
├── data/config.json
├── actions/
│   ├── Lldp.php
│   └── LldpData.php
├── views/
│   └── lldp.php
└── lib/
    ├── LanguageManager.php
    ├── ViewRenderer.php
    ├── ZabbixVersion.php
    ├── ConfigManager.php
    ├── TemplateHostResolver.php
    └── LldpTopologyBuilder.php
```

## Configuração do módulo

### Dependência de template

O módulo espera um template do Zabbix chamado **Lldp Discovery** (configurável). O template deve descobrir itens com a chave `lldp.remote.info[{#LOCAL_PORT}]` cujo `lastvalue` tenha o formato:

```
DISPOSITIVO_REMOTO(PORTA_REMOTA)
```

Exemplo: `YJZHZX-LouCengHuiJu-S7510E(GigabitEthernet2/2/0/1)`

Vincule o template aos hosts que devem aparecer na topologia e garanta que os itens de LLD tenham dados recentes.

### Configuração

As configurações ficam em `data/config.json`:

```json
{
    "template_name": "Lldp Discovery",
    "item_key_prefix": "lldp.remote.info",
    "discovery_key": "lldp_discovery"
}
```

| Campo | Descrição |
|-------|-------------|
| `template_name` | Nome visível do template (o nome técnico também é testado) |
| `item_key_prefix` | Prefixo da chave usado para buscar os itens de vizinhos LLDP |
| `root_hostid` | ID de host do Zabbix opcional, usado como raiz quando presente no grafo. |
| `discovery_key` | Chave da regra de LLD, reservada para uso futuro |

### Uso

1. Abra **Monitoramento → Topologia LLDP**.
2. Selecione o grupo de hosts e o host (padrão: todos os grupos, todos os hosts).
3. Clique em **Consultar** para carregar a topologia.
4. Passe o mouse sobre enlaces ou nós para ver detalhes de portas e dispositivos.

## Licença

Este módulo segue os termos de licença do Zabbix. Consulte: https://www.zabbix.com/license
