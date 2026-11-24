# Módulo Zabbix JumpServer

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend do Zabbix que sincroniza (envia) hosts e grupos de hosts do Zabbix para um bastion host JumpServer e permite ir, com um clique, do Zabbix para a página de conexão do host no JumpServer. Ele adiciona o menu **JumpServer** à seção **Inventário** do Zabbix Web.

Ao enviar hosts, o módulo automaticamente:

- Cria nós no JumpServer para os grupos de hosts do Zabbix (criados se não existirem)
- Detecta a plataforma do host (Linux / Windows) pelo item "Sistema operacional" do Zabbix
- Cria ou atualiza os ativos no JumpServer e grava o ID do ativo do JumpServer no host do Zabbix como tag, para uso do botão "Conectar"

![1](images/1.png)

## Recursos

- **Filtros de grupo / host**: no topo da página há uma lista de grupos de hosts e uma de hosts; por padrão, todos os grupos e hosts são exibidos
- **Filtro por status de alarme**: filtra os hosts por severidade (Desastre, Alta, Média, Atenção, Informação, Não classificada, OK); por padrão, todos os estados são exibidos
- **Pesquisa por IP / nome do host**: encontra hosts rapidamente pelo endereço IP ou pelo nome
- **Enviar todos os grupos de hosts**: envia todos os grupos de hosts do Zabbix como nós do JumpServer com um clique; os ausentes são criados automaticamente
- **Enviar todos os hosts**: envia todos os hosts do Zabbix como ativos do JumpServer com um clique (atribuição automática de nó e detecção de plataforma)
- **Obter IDs de ativos do JumpServer**: busca todos os ativos do JumpServer, associa os hosts do Zabbix pelo IP e grava os IDs dos ativos nas tags dos hosts
- **Envio / reenvio de um host**: hosts ainda não enviados podem ser enviados individualmente; passe o mouse sobre o rótulo de status de um host já enviado para reenviá-lo
- **Status de alarme**: a coluna "Status de alarme" mostra a contagem por severidade (ex.: "Alta 2", "Atenção 1"); mostra "OK" quando não há alarmes
- **Detalhes de alarmes expansíveis**: clique no controle de expansão no início de cada linha para ver todos os alarmes ativos do host (severidade, nome, hora)
- **Conexão com um clique**: hosts já enviados mostram um botão "Conectar" na última coluna, que leva à página de conexão do host no JumpServer
- **Persistência do ID do ativo**: o ID do ativo do JumpServer é guardado como tag no host do Zabbix (`jumpserver_asset_id`)
- **Internacionalização**: interface em chinês, inglês, russo e português do Brasil

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_jumpserver/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o módulo "Zabbix JumpServer" e ative-o.
4. Atualize a página. O módulo aparece no menu **Inventário** como "JumpServer".

## Observações

- **Rede**: o servidor web do Zabbix precisa alcançar a URL do JumpServer.
- **Permissões**: ver a página exige privilégios de usuário comum; enviar e obter IDs de ativos exige privilégios de Admin no Zabbix.
- **Detecção de plataforma**: a plataforma do host depende do valor do item "Sistema operacional" do Zabbix; na ausência dele, assume-se Linux.
- **Gravação da tag**: após o envio, a tag `jumpserver_asset_id` é gravada no host do Zabbix; as tags existentes são preservadas.
- **Segurança das credenciais**: `data/config.json` contém credenciais sensíveis; controle as permissões do arquivo e não o publique em repositórios públicos.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Estrutura de arquivos:

- `manifest.json`: configuração do módulo
- `Module.php`: registro do menu (Inventário → JumpServer)
- `actions/Jumpserver.php`: lógica da página principal (filtros, pesquisa, estatísticas de alarmes, tabela de hosts)
- `actions/JumpserverPush.php`: envio de hosts/grupos e gravação do ID do ativo
- `actions/JumpserverFetchIds.php`: obtém os IDs de ativos do JumpServer e grava as tags pelo IP
- `views/jumpserver.php`: view da página
- `lib/JumpserverClient.php`: cliente da API do JumpServer (autenticação HTTP Signature)
- `lib/ConfigManager.php`: lê as credenciais de data/config.json
- `lib/LanguageManager.php`: gerenciamento de idiomas (inclui o dicionário `pt_BR`)
- `lib/ViewRenderer.php`: utilitários de renderização das views
- `lib/ZabbixVersion.php`: utilitários de compatibilidade entre versões
- `data/config.json`: credenciais de conexão com o JumpServer

Para extensões, consulte a [documentação de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

### Configuração

As credenciais ficam em `data/config.json`:

```json
{
    "jumpserver_url": "http://192.168.3.29",
    "access_key_id": "<AccessKeyID>",
    "access_key_secret": "<AccessKeySecret>",
    "org_id": "00000000-0000-0000-0000-000000000002",
    "connect_url_template": "{base_url}/luna/connect?asset={asset_id}",
    "account_template_id": "",
    "verify_ssl": false
}
```

| Campo | Descrição |
|-------|-------------|
| `jumpserver_url` | URL base do JumpServer |
| `access_key_id` | AccessKey ID do usuário no JumpServer |
| `access_key_secret` | AccessKey Secret do usuário no JumpServer |
| `org_id` | ID da organização; o padrão é a organização DEFAULT |
| `connect_url_template` | Modelo da URL de conexão; `{base_url}` e `{asset_id}` são substituídos |
| `account_template_id` | ID do template de contas; deixe vazio para não vincular contas automaticamente |
| `verify_ssl` | Se o certificado HTTPS deve ser verificado |

**Como obter uma AccessKey**: entre no JumpServer → interface do usuário → Perfil → API Key (AccessKey); crie uma para obter o ID e o Secret. O módulo se autentica com HTTP Signature (hmac-sha256). Consulte a [documentação da API REST do JumpServer](https://docs.jumpserver.org/zh/v4/dev/rest_api/).

#### Vínculo automático de contas

Quando `account_template_id` está definido, o módulo vincula automaticamente uma conta de login, a partir do template de contas, **ao criar** um ativo no JumpServer (ativos existentes não são alterados):

1. Crie um **template de contas** no JumpServer (Contas → Templates de contas) com usuário e senha ou chave SSH.
2. Copie o ID do template de contas e defina-o como `account_template_id` em `data/config.json`.
3. Ao enviar hosts, o módulo inclui o template no campo `accounts` **somente na criação de novos ativos** (`POST /api/v1/assets/hosts/`, como `[{"template": "<id-do-template>"}]`); o JumpServer preenche o usuário/segredo a partir do template e vincula a conta.

Observações:

- **Somente na criação**: atualizações de ativos existentes não incluem o campo `accounts`, então as contas já vinculadas no JumpServer não são afetadas.
- O usuário dono da AccessKey precisa da permissão de gerenciamento de contas (`accounts.add_account`).
- As credenciais vêm do template; se você precisa que o JumpServer de fato crie/altere a conta de sistema no host de destino, ative o envio automático no template de contas e configure uma conta privilegiada.

#### Sincronização reversa dos IDs de ativos

Clique em **Obter IDs de ativos do JumpServer** para:

1. Buscar todos os ativos de host do JumpServer (`/api/v1/assets/hosts/`).
2. Associar o `address` (IP) de cada ativo ao IP da interface principal de cada host do Zabbix.
3. Gravar o ID do ativo correspondente na tag `jumpserver_asset_id` do host (ignorado se o mesmo ID já estiver definido).

Útil quando os ativos já existem no JumpServer, mas os hosts do Zabbix ainda não têm a tag com o ID do ativo.

### Configurar as credenciais

Edite `zabbix_jumpserver/data/config.json` e preencha a URL do JumpServer e a AccessKey. Garanta que o usuário web do Zabbix consiga ler esse arquivo.

## Licença

Este projeto segue a licença do Zabbix. Para mais detalhes, consulte a [Licença do Zabbix](https://www.zabbix.com/license).
