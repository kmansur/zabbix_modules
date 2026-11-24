# Assistente de sincronização de IM

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

Módulo de frontend do Zabbix que sincroniza os departamentos do **WeCom**, **Feishu** ou **DingTalk** com os **grupos de usuários** do Zabbix e atribui os membros de cada departamento aos grupos correspondentes.

O módulo adiciona o submenu **Assistente de sincronização de IM** ao menu **Usuários** do Zabbix Web.

## Recursos

- **Várias plataformas**: WeCom, Feishu, DingTalk
- **Várias configurações de sincronização**: gerencie as credenciais de IM pela interface (**Usuários → Configurações de sincronização**); apenas uma configuração ativa por vez
- **Sincronização de departamentos**: cria/atualiza grupos de usuários do Zabbix a partir dos departamentos do IM
- **Sincronização de usuários**: associa ou cria usuários do Zabbix a partir dos membros do IM e os atribui aos grupos
- **Política de nomes de usuário no DingTalk**: prioriza o celular; sem celular, converte o `name` para pinyin (ex.: 张三 → `zhangsan`); por último usa o `userid`
- **Correspondência flexível**: por nome de usuário, e-mail ou alias (atualmente fixa em nome de usuário)
- **Modo de pré-visualização**: pré-visualize a correspondência de departamentos e usuários antes de sincronizar
- **Modo caminho**: opcionalmente usa o caminho completo do departamento como nome do grupo
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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_im/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o módulo **IM Sync Assistant** e ative-o.
4. Atualize a página. O **Assistente de sincronização de IM** aparece no menu **Usuários**.

## Observações

### Permissões

- Ver a página e sincronizar departamentos exige privilégios de **Admin do Zabbix**.
- **Configurações de sincronização** (criar/editar/excluir credenciais, ativar/desativar) exige **Super Admin** (envolve segredos de aplicativos).
- **Sincronizar todos os usuários** (com criação automática de usuários) exige **Super Admin** (no Zabbix 7, `user.create` é exclusivo de Super Admin).
- Os aplicativos de IM precisam ter as permissões de API de contatos/catálogo de endereços habilitadas.

- **Política de proteção**: usuários/grupos nativos ou criados manualmente **nunca são excluídos nem alterados**; o módulo gerencia apenas os objetos registrados em `data/sync_registry.json`.
- **Tipos de usuário**:
  - `created`: criado automaticamente pelo módulo; pode ser adicionado/atualizado/removido conforme as mudanças no IM.
  - `linked`: associado a um usuário existente do Zabbix; apenas a participação em grupos é sincronizada; **a conta não é excluída**.
- **Conflitos de grupo**: se já existir um grupo com o mesmo nome que não foi criado pelo módulo, o departamento é ignorado e registrado em log.
- **Membros manuais**: usuários não gerenciados pelo módulo adicionados manualmente a grupos gerenciados pelo módulo são preservados na sincronização.
- **Usuários criados automaticamente**: as senhas são geradas automaticamente (12 caracteres, maiúsculas/minúsculas/dígitos) e exibidas **uma única vez** no resultado de "Sincronizar todos os usuários" — guarde-as logo; o Zabbix 7+ também exige `default_roleid`; execute a sincronização como Super Admin.
- **Exclusão manual de usuários**: se você excluir um usuário sincronizado no Zabbix, a próxima sincronização limpa o registro e recria o usuário.
- **Usuários em vários departamentos**: usuários presentes em vários departamentos do IM são adicionados a vários grupos de usuários do Zabbix.
- **Acesso à rede**: o Zabbix Web precisa alcançar as APIs da plataforma de IM.
- **Mudança de nome de usuário no DingTalk**: se os usuários foram sincronizados antes pelo `userid`, mudar para celular/pinyin pode criar contas novas em vez de associar as antigas; pré-visualize antes de sincronizar.
- **Extensões do PHP**: a conversão de nomes para pinyin do DingTalk exige `iconv` ou `mbstring` (qualquer uma basta).

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix:

- `manifest.json`: configuração do módulo
- `Module.php`: registro do menu (Sincronização de IM / Configurações de sincronização)
- `actions/Im.php`: lógica da página principal
- `actions/ImSync.php` / `ImSyncUsers.php`: APIs de sincronização
- `actions/ImPreview.php`: API de pré-visualização
- `actions/ImSettings.php` / `ImSettingsSave.php` / `ImSettingsDelete.php` / `ImSettingsEnable.php`: CRUD das configurações de sincronização
- `views/im.php` / `im.settings.php`: views das páginas
- `lib/ImSyncService.php`: lógica central de sincronização
- `lib/WeComClient.php` / `FeishuClient.php` / `DingTalkClient.php`: clientes das plataformas de IM
- `lib/PinyinHelper.php`: conversão de nomes chineses para pinyin (DingTalk sem celular)
- `lib/ConfigManager.php`: configuração (várias `settings`, apenas uma ativa, migração do formato antigo)
- `lib/SyncRegistry.php`: registro dos objetos criados pelo módulo
- `lib/LanguageManager.php`: internacionalização
- `data/config.json`: configurações de conexão (lista `settings`)
- `data/sync_registry.json`: registro de sincronização (gerado em tempo de execução)

### Internacionalização

Todos os textos da interface ficam em `lib/LanguageManager.php`, em **chinês (zh_CN)**, **inglês (en_US)**, **russo (ru_RU)** e **português do Brasil (pt_BR)**. A detecção de idioma segue o Zabbix: idioma do usuário → padrão do sistema → `en_US`. Menus, páginas, diálogos, mensagens de erro da API e exceções dos clientes de IM são traduzidos; códigos e mensagens de erro brutos das APIs de terceiros ainda podem aparecer em inglês.

## Configuração do módulo

### Configurar o provedor de IM

As credenciais são gerenciadas pela interface: acesse **Usuários → Configurações de sincronização** (exige **Super Admin**).

- Clique em **Adicionar configuração de sincronização**, escolha o provedor (WeCom / Feishu / DingTalk), preencha as chaves e o ID do departamento raiz e salve. Todas as configurações são listadas em uma tabela.
- **Apenas uma configuração pode estar ativa por vez**: ativar uma desativa automaticamente as demais. A página **Sincronização de IM** sempre usa a configuração ativa.
- Na edição, os campos secretos ficam em branco por padrão, o que significa **manter o valor existente**; apenas um valor novo o sobrescreve.
- Tudo é persistido na lista `settings` de `zabbix_im/data/config.json`. Não é preciso editar o arquivo manualmente nem reiniciar serviços.

Estrutura do `config.json` (a lista `settings` é gerenciada pela interface; as opções globais podem ser editadas à mão):

```json
{
    "use_full_path": false,
    "path_separator": "/",
    "username_lowercase": true,
    "remove_orphans": true,
    "remove_orphan_users": true,
    "auto_create_users": true,
    "auto_update_users": true,
    "default_roleid": "",
    "default_user_type": 2,
    "verify_ssl": true,
    "settings": [
        {
            "id": "set_default_wecom",
            "name": "WeCom",
            "provider": "wecom",
            "enabled": true,
            "root_department_id": "1",
            "corp_id": "your_corp_id",
            "corp_secret": "your_corp_secret",
            "app_id": "",
            "app_secret": "",
            "app_key": ""
        }
    ]
}
```

> Compatibilidade com o formato antigo: se o `config.json` ainda usar o formato antigo (`provider` no nível superior mais as seções `wecom`/`feishu`/`dingtalk`), o módulo o migra para a lista `settings` ao carregar, ativando o provedor que corresponde ao valor antigo de `provider`.

##### Referência de configuração

> O `provider` e as credenciais de cada provedor são informados pela interface **Configurações de sincronização**; as opções globais abaixo ainda podem ser ajustadas no nível superior do `config.json`.

**Opções globais**

| Campo | Tipo | Descrição |
|-------|------|-------------|
| `settings[].provider` | string | Fonte de dados: `wecom` / `feishu` / `dingtalk`. Informado por configuração em Configurações de sincronização; apenas a configuração ativa é usada. |
| `use_full_path` | bool | Usa o caminho completo do departamento no IM como nome do grupo de usuários no Zabbix. `false` = apenas o nome do departamento (ex.: "Equipe P&D A"); `true` = caminho completo (ex.: "Centro de P&D/Equipe P&D A"), com o separador de `path_separator`. |
| `path_separator` | string | Separador do caminho, padrão `/`. Só se aplica quando `use_full_path` é `true`. |
| `username_lowercase` | bool | Converte os nomes de usuário para minúsculas ao associar/criar (o userid do WeCom costuma misturar maiúsculas e minúsculas). |
| `remove_orphans` | bool | Exclui grupos de usuários **gerenciados pelo módulo** quando os departamentos somem do IM. Grupos manuais ou nativos nunca são excluídos. |
| `remove_orphan_users` | bool | Exclui usuários do Zabbix **criados pelo módulo** quando somem do IM. Apenas contas `origin=created`; usuários `linked` nunca são excluídos. |
| `auto_create_users` | bool | Cria automaticamente usuários no Zabbix para membros do IM não encontrados. "Sincronizar todos os usuários" exige **Super Admin**; o Zabbix 7+ exige `default_roleid`. |
| `auto_update_users` | bool | Atualiza nome/e-mail dos usuários criados pelo módulo. Usuários `linked` não são alterados. |
| `default_roleid` | string | ID do perfil para usuários criados automaticamente no Zabbix 7.0+ (veja **Usuários → Perfis de usuário**). Se vazio, o módulo tenta escolher um perfil não administrativo cujo nome contenha `user`; recomenda-se informar um valor explícito. |
| `default_user_type` | int | Tipo de usuário para usuários criados automaticamente no Zabbix 6.x: `1` Usuário / `2` Admin / `3` Super Admin (normalmente `1` ou `2`). |
| `verify_ssl` | bool | Verifica os certificados HTTPS ao chamar as APIs de IM. Use `false` para proxies internos ou certificados autoassinados. |

##### Regras de nome de usuário no Zabbix por plataforma

O módulo sempre associa/cria usuários pelo **nome de usuário**. Origem por plataforma:

| Plataforma | Origem do nome de usuário no Zabbix | Observações |
|----------|------------------------|-------|
| WeCom | `userid` | Igual à conta de contato no console de administração |
| Feishu | `user_id` | Exige `contact:user.employee_id:readonly`; na falta, usa `open_id` |
| DingTalk | `mobile` → pinyin do nome → `userid` | Converte `name` para pinyin em minúsculas quando não há celular (tabela GB2312 embutida); nomes ASCII são usados como estão; caracteres raros/não mapeados recorrem ao `userid` |

> A conversão para pinyin do DingTalk exige `iconv` ou `mbstring` no PHP (normalmente disponíveis no Zabbix). Nomes idênticos sem celular podem gerar nomes de usuário em pinyin duplicados.

**WeCom `wecom` (`provider = wecom`)**

Este módulo chama as APIs de contatos do WeCom por meio de um **aplicativo próprio da empresa**, usando `access_token` (obtido com o `corp_id` + `corp_secret` do aplicativo). Documentação oficial: [Centro do desenvolvedor do WeCom](https://developer.work.weixin.qq.com/document/).

| Campo | Descrição |
|-------|-------------|
| `corp_id` | Corp ID. Console de administração → **Minha empresa → Informações da empresa → Corp ID** |
| `corp_secret` | **Secret** do aplicativo próprio. Console de administração → **Gerenciamento de aplicativos → Aplicativos → Próprios** → abrir o aplicativo → Secret |
| `root_department_id` | ID do departamento raiz. `1` normalmente significa sincronizar a partir da raiz da empresa e todos os subdepartamentos; ou um ID de departamento específico para sincronizar apenas essa subárvore |

> **Importante**: use o **Secret de um aplicativo próprio**, e não o Secret de sincronização de contatos de **Ferramentas de administração → Sincronização de contatos**. Desde 15/08/2022, o assistente de sincronização de contatos restringe novos IPs nas APIs de detalhes de membros/departamentos (erro `48009`). Veja [Mudanças na API de sincronização de contatos](https://developer.work.weixin.qq.com/document/path/90193).

##### Configuração do aplicativo no WeCom

1. **Crie um aplicativo próprio**  
   Entre no [console de administração do WeCom](https://work.weixin.qq.com/wework_admin/frame) → **Gerenciamento de aplicativos → Aplicativos → Próprios → Criar aplicativo**. Este módulo só precisa das APIs de contatos do lado do servidor; não é necessário OAuth web nem URL de callback (a menos que, no futuro, você precise de OAuth para campos sensíveis).

2. **Obtenha as credenciais**  
   - `corp_id`: **Minha empresa → Informações da empresa → Corp ID**  
   - `corp_secret`: **Gerenciamento de aplicativos → Próprios → aplicativo → Secret**  
   Informe os dois em **Usuários → Configurações de sincronização** no Zabbix.

3. **Configure o escopo de visibilidade do aplicativo (essencial)**  
   Aplicativos próprios **só leem os contatos dentro do seu escopo de visibilidade**, o que também limita o acesso às APIs. Veja [Conceitos básicos – escopo de visibilidade do aplicativo](https://developer.work.weixin.qq.com/document/path/90665).

   Caminho: **Gerenciamento de aplicativos → Próprios → aplicativo → Escopo de visibilidade**

   | Objetivo da sincronização | Escopo de visibilidade recomendado |
   |-----------|------------------------------|
   | Organização inteira | Defina como o **departamento raiz** (ou um escopo que cubra todos os departamentos a sincronizar) |
   | Uma única linha de negócio | Defina como esse departamento (subdepartamentos e membros são incluídos automaticamente) |

   > **Configuração típica**: o escopo de visibilidade da área de trabalho pode ser apenas TI/operações, mas para a sincronização completa o escopo precisa cobrir **todos os departamentos a sincronizar**. Caso contrário, as APIs retornam `60011` (sem permissão para o membro/departamento/tag informado).

4. **APIs usadas por este módulo**

   | Finalidade | API do WeCom | Documentação |
   |---------|-----------|------|
   | Token de acesso | `GET /cgi-bin/gettoken` | [Obter access_token](https://developer.work.weixin.qq.com/document/path/91039) |
   | Lista de departamentos (com subdepartamentos) | `GET /cgi-bin/department/list?id={root_department_id}` | [Obter lista de departamentos](https://developer.work.weixin.qq.com/document/path/90208) |
   | Membros diretos do departamento | `GET /cgi-bin/user/list?department_id={id}&fetch_child=0` | [Obter detalhes dos membros do departamento](https://developer.work.weixin.qq.com/document/path/90201) |

   Fluxo de sincronização: busca a árvore de departamentos a partir de `root_department_id` e depois chama `user/list` para cada departamento com `fetch_child=0`, obtendo **apenas os membros diretos**, conforme a orientação recursiva do WeCom.

5. **Permissões e campos retornados**

   - **Permissão de API**: o aplicativo precisa de **permissão de visualização** sobre os departamentos/membros de destino (dentro do escopo de visibilidade). Veja [Visão geral do gerenciamento de contatos](https://developer.work.weixin.qq.com/document/path/90193).
   - **Nome de usuário no Zabbix**: o módulo usa o **`userid`** do WeCom (igual à conta no console de administração).
   - **Nome**: normalmente retornado por `user/list`; pode ficar vazio no resultado se não vier.
   - **Celular / e-mail (campos sensíveis)**: desde **20/06/2022**, novos aplicativos próprios não recebem mais celular, e-mail e biz_mail pelas APIs comuns de contatos; é necessária autorização manual via OAuth2 (`snsapi_privateinfo`) por usuário. Veja [Ler membro](https://developer.work.weixin.qq.com/document/path/90196) e [Obter detalhes dos membros do departamento](https://developer.work.weixin.qq.com/document/path/90201).
     - Este módulo **não implementa OAuth**; a sincronização continua funcionando (userid, departamentos, participação em grupos), mas **celular/e-mail podem ficar vazios**.
     - Aplicativos mais antigos ainda podem retornar `mobile`, `email`, `biz_mail`; o módulo prioriza `email` e depois `biz_mail`.

6. **(Opcional) IP confiável da empresa**  
   Se você usa o Secret de sincronização de contatos ou tem lista de IPs permitidos ativa, adicione o IP de saída do servidor do Zabbix Web em **Ferramentas de administração → Sincronização de contatos → IP confiável**. Normalmente não é necessário com o Secret de aplicativo próprio.

7. **Ative no Zabbix**  
   **Usuários → Configurações de sincronização** → adicione uma configuração WeCom → preencha Corp ID, Corp Secret e `root_department_id` → salve e ative → pré-visualize/sincronize em **Sincronização de IM**.

##### Exemplo de configuração do WeCom

```json
{
    "id": "set_wecom_prod",
    "name": "WeCom Produção",
    "provider": "wecom",
    "enabled": true,
    "root_department_id": "1",
    "corp_id": "wwxxxxxxxxxxxxxxxx",
    "corp_secret": "your_app_secret"
}
```

##### Solução de problemas do WeCom

| Sintoma / erro | Causa provável | Solução |
|-----------------|--------------|-----|
| `40001` secret inválido | `corp_id` / `corp_secret` não correspondem, ou o Secret foi redefinido / o aplicativo foi desativado | Confira as credenciais; copie o Secret novamente do console |
| `40014` access_token inválido | Token expirado ou Secret do aplicativo errado | O módulo renova automaticamente; confirme o Secret do aplicativo próprio correto |
| `60011` sem permissão para departamento/membro/tag | Destino fora do **escopo de visibilidade** do aplicativo | Amplie o escopo de visibilidade ou ajuste `root_department_id` |
| `48009` API sem permissão | Secret de sincronização de contatos + IP não liberado | Use o **Secret de aplicativo próprio** ou configure o IP confiável |
| `48002` API sem permissão | Tipo de Secret errado para a API | Use o Secret de aplicativo próprio; verifique as notas de permissão da API |
| `40066` lista de departamentos inválida | `root_department_id` ausente ou fora do escopo | Confirme o ID do departamento em **Contatos → Organização** |
| Menos departamentos/usuários que o esperado | Escopo de visibilidade restrito demais | Amplie para a raiz ou para todos os departamentos necessários |
| Celular / e-mail sempre vazios | Política de campos sensíveis para aplicativos novos | Esperado; exige OAuth (não suportado por este módulo) |
| Divergência de maiúsculas/minúsculas no nome de usuário | O nome de usuário no Zabbix difere do userid | Defina `"username_lowercase": true` na configuração (ativado por padrão) |

> Termos: [Conceitos básicos](https://developer.work.weixin.qq.com/document/path/90665). Códigos de erro: [Códigos de erro globais](https://developer.work.weixin.qq.com/document/path/90313).

**Feishu `feishu` (`provider = feishu`)**

Este módulo chama as APIs de contatos do Feishu por meio de um **aplicativo próprio da empresa**, usando `tenant_access_token` (identidade do aplicativo). Documentação oficial: [Plataforma aberta do Feishu](https://open.feishu.cn/document/home/index).

| Campo | Descrição |
|-------|-------------|
| `app_id` | **App ID** do aplicativo próprio (console do desenvolvedor → detalhes do aplicativo → Credenciais e informações básicas) |
| `app_secret` | **App Secret** do aplicativo próprio |
| `root_department_id` | ID do departamento raiz. `"0"` = sincroniza recursivamente a partir da raiz da empresa; ou um `open_department_id` específico (ex.: `od-xxxxxxxx`) para apenas essa subárvore |

##### Configuração do aplicativo no Feishu

1. **Crie um aplicativo próprio**  
   Entre no [console do desenvolvedor do Feishu](https://open.feishu.cn/app) e siga o [processo de desenvolvimento de aplicativo próprio](https://open.feishu.cn/document/home/introduction-to-custom-app-development/self-built-application-development-process). Apenas APIs do lado do servidor; não é necessário bot nem aplicativo web.

2. **Obtenha as credenciais**  
   Copie o **App ID** e o **App Secret** de **Credenciais e informações básicas** para **Usuários → Configurações de sincronização** no Zabbix.

3. **Habilite as permissões de API (Gerenciamento de permissões → Permissões de API)**

   | Finalidade | API do Feishu | Documentação |
   |---------|------------|------|
   | Token de acesso | `POST /auth/v3/tenant_access_token/internal` | [Obter tenant_access_token](https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal) |
   | Departamento raiz/único | `GET /contact/v3/departments/{department_id}` | [Obter departamento](https://open.feishu.cn/document/server-docs/contact-v3/department/get) |
   | Departamentos filhos | `GET /contact/v3/departments` | [Listar departamentos filhos](https://open.feishu.cn/document/server-docs/contact-v3/department/children) |
   | Membros diretos do departamento | `GET /contact/v3/users/find_by_department` | [Buscar usuários por departamento](https://open.feishu.cn/document/server-docs/contact-v3/user/find_by_department) |

   **Permissões de API de contatos recomendadas (qualquer uma habilita as APIs acima):**

   | Escopo | Nome | Observações |
   |-------|------|-------|
   | `contact:contact:readonly_as_app` | Ler contatos como aplicativo | **Recomendado**; corresponde ao uso de `tenant_access_token` |
   | `contact:contact:readonly` | Ler contatos | Também funciona |
   | `contact:contact:access_as_app` | Acessar contatos como aplicativo | Também funciona |
   | `contact:contact.base:readonly` | Ler informações básicas de contatos | Também funciona |
   | `contact:department.organize:readonly` | Ler a estrutura organizacional | Também funciona para listar departamentos filhos |

4. **Configure o escopo de contatos (essencial)**  
   Além das permissões de API, é preciso definir o **escopo de permissão de contatos** (quais departamentos/usuários o aplicativo pode acessar); caso contrário, as APIs retornam `40004 no dept authority error`. Veja [Autoridade de escopo](https://open.feishu.cn/document/server-docs/contact-v3/scope/scope_authority).

   Caminhos de configuração:
   - Console do desenvolvedor → aplicativo → **Configuração de desenvolvimento → Gerenciamento de permissões** → permissões de contatos → **Escopo de dados acessíveis → Configurar**
   - Administração do Feishu → **Área de trabalho → Gerenciamento de aplicativos** → aplicativo → **Configurações de contatos**

   | `root_department_id` | Requisito de escopo de contatos |
   |----------------------|---------------------------|
   | `"0"` (raiz da empresa) | Precisa ser **Todos os membros** (escopo da empresa inteira). As APIs do departamento raiz verificam a permissão sobre a empresa inteira |
   | Departamento específico (ex.: `od-xxx`) | O escopo precisa **incluir esse departamento e todos os subdepartamentos** |

   > **Configuração típica**: a disponibilidade do aplicativo pode ser só para TI, mas o escopo de contatos deve ser **Todos os membros** para sincronizar a organização inteira. Veja o [caso de exemplo oficial](https://open.feishu.cn/document/server-docs/contact-v3/scope/scope_authority#典型案例).

5. **Permissões de campos (conforme necessário)**  
   Campos sensíveis são omitidos, a menos que o escopo correspondente seja concedido:

   | Uso no módulo | Escopo recomendado | Identificador |
   |------------|-------------------|------------|
   | Usar o `user_id` do Feishu como nome de usuário no Zabbix | Ler ID do usuário | `contact:user.employee_id:readonly` |
   | Sincronizar e-mail | Ler e-mail do usuário | `contact:user.email:readonly` |
   | Sincronizar celular | Ler telefone do usuário | `contact:user.phone:readonly` |
   | Sincronizar nome de exibição | Ler informações básicas do usuário | `contact:user.base:readonly` |

   Sem `contact:user.employee_id:readonly`, o módulo recorre ao `open_id` (que muda de aplicativo para aplicativo; recomenda-se habilitar o `user_id`).

6. **Publique uma versão do aplicativo**  
   Após alterar permissões ou escopo, crie e publique uma nova versão; é necessária a aprovação do administrador da empresa. Veja [Publicar aplicativo próprio](https://open.feishu.cn/document/home/introduction-to-custom-app-development/self-built-application-development-process#发布应用).

7. **Ative no Zabbix**  
   **Usuários → Configurações de sincronização** → adicione uma configuração Feishu → App ID, App Secret, `root_department_id` → salve e ative → pré-visualize/sincronize em **Sincronização de IM**.

##### Exemplo de configuração do Feishu

```json
{
    "id": "set_feishu_prod",
    "name": "Feishu Produção",
    "provider": "feishu",
    "enabled": true,
    "root_department_id": "0",
    "app_id": "cli_xxxxxxxxxx",
    "app_secret": "your_app_secret"
}
```

##### Solução de problemas do Feishu

| Sintoma / erro | Causa provável | Solução |
|-----------------|--------------|-----|
| `40004 no dept authority error` | Departamento de destino fora do escopo de contatos | Amplie o escopo ou use um `root_department_id` dentro do escopo |
| Falha em usuários/subdepartamentos do departamento raiz | `root_department_id = 0` sem escopo da empresa inteira | Defina o escopo de contatos como **Todos os membros** |
| Nome de usuário é `ou_xxx` e não user_id | Falta `contact:user.employee_id:readonly` | Conceda o escopo e publique o aplicativo novamente |
| E-mail / celular vazios | Faltam escopos de campos | Conceda `contact:user.email:readonly` e `contact:user.phone:readonly` |
| Permissões alteradas sem efeito | Nenhuma versão nova / administrador não aprovou | Publique a versão → aprovação do administrador |
| Falha no `tenant_access_token` | `app_id`/`app_secret` errados ou aplicativo desativado | Confira as credenciais e o status do aplicativo |

> Lista completa de escopos: [Lista de permissões de aplicativos](https://open.feishu.cn/document/ukTMukTMukTM/uYTM5UjL2ETO14iNxkTN/scope-list). Tipos de ID de usuário: [Como obter os diferentes IDs de usuário](https://open.feishu.cn/document/home/user-identity-introduction/open-id).

**DingTalk `dingtalk` (`provider = dingtalk`)**

Este módulo chama as APIs de contatos do DingTalk por meio de um **aplicativo interno da empresa**, usando `access_token` (obtido com `app_key` + `app_secret`). Documentação oficial: [Plataforma aberta do DingTalk](https://open.dingtalk.com/document/).

| Campo | Descrição |
|-------|-------------|
| `app_key` | **AppKey** (Client ID) do aplicativo interno. Console do desenvolvedor → aplicativo → **Credenciais e informações básicas** |
| `app_secret` | **AppSecret** (Client Secret) do aplicativo interno |
| `root_department_id` | ID do departamento raiz. `1` = sincroniza recursivamente a partir da raiz da empresa; ou um ID de departamento específico para apenas essa subárvore |

> **Importante**: use um **aplicativo interno da empresa**, e não um aplicativo empresarial de terceiros. Aplicativos de terceiros normalmente **não retornam** celular, e-mail etc.; este módulo exige um aplicativo interno.

##### Configuração do aplicativo no DingTalk

1. **Crie um aplicativo interno**  
   Entre como administrador na [plataforma aberta do DingTalk](https://open.dingtalk.com/) → **Desenvolvimento de aplicativos → Desenvolvimento interno → Criar aplicativo**. Apenas APIs de contatos do lado do servidor. Tutorial: [Obter todos os funcionários de uma empresa](https://open.dingtalk.com/document/orgapp/obtains-information-about-all-employees-of-an-enterprise).

2. **Obtenha as credenciais**  
   Copie o **AppKey** e o **AppSecret** de **Credenciais e informações básicas** para **Usuários → Configurações de sincronização** no Zabbix.

3. **Solicite as permissões de API (Configuração de desenvolvimento → Gerenciamento de permissões)**  
   As APIs de servidor do DingTalk são autorizadas **por aplicativo**. Pesquise e solicite:

   | Código da permissão | Nome da permissão | Uso no módulo |
   |-----------------|-----------------|------------|
   | `qyapi_get_department_list` | Leitura de informações de departamentos | Detalhes do departamento, lista de departamentos filhos |
   | `qyapi_get_department_member` | Leitura de membros de departamentos | Lista de usuários do departamento (paginada) |

   Caminho: **Detalhes do aplicativo → Configuração de desenvolvimento → Gerenciamento de permissões → pesquisar códigos → Solicitar**. Algumas permissões exigem aprovação do administrador.

4. **(Recomendado) Permissões de informações pessoais**  
   Para ter celular/e-mail no resultado da sincronização, conceda também as permissões de leitura de **informações pessoais / celular / e-mail** dos contatos (os nomes exatos variam no console).
   - **Aplicativo interno**: normalmente retorna `mobile`, `email`, `org_email` (o módulo prioriza `email` e depois `org_email`).
   - **Aplicativo de terceiros**: a documentação oficial informa que celular/e-mail **não são retornados** — não é adequado para este módulo.

5. **(Opcional) IP de saída do servidor**  
   Em **Configuração de desenvolvimento → Gerenciamento de desenvolvimento**, defina o **IP de saída do servidor** (IP público do Zabbix Web) se houver lista de IPs permitidos.

6. **APIs usadas por este módulo**

   | Finalidade | API do DingTalk | Documentação |
   |---------|--------------|------|
   | Token de acesso | `GET /gettoken?appkey=&appsecret=` | [Obter access_token de aplicativo interno](https://open.dingtalk.com/document/orgapp/obtain-orgapp-token) |
   | Departamento raiz/único | `POST /topapi/v2/department/get` | [Obter detalhes do departamento](https://open.dingtalk.com/document/orgapp/query-department-details0-v2) |
   | Subdepartamentos do próximo nível | `POST /topapi/v2/department/listsub` | [Listar subdepartamentos](https://open.dingtalk.com/document/orgapp/obtain-the-department-list) |
   | Membros diretos do departamento | `POST /topapi/v2/user/list` | [Obter detalhes dos usuários do departamento](https://open.dingtalk.com/document/orgapp/queries-the-complete-information-of-a-department-user) |

   Fluxo de sincronização (conforme a [orientação oficial de sincronização completa](https://open.dingtalk.com/document/orgapp/obtains-information-about-all-employees-of-an-enterprise)):
   - Adiciona primeiro o departamento raiz (o `listsub` não inclui a raiz);
   - Busca recursivamente os subdepartamentos via `listsub` a partir de `root_department_id`;
   - Para cada departamento, chama `user/list` com paginação por `cursor` (`size` máximo 100), **apenas membros diretos**;
   - Exclui automaticamente o departamento de contatos escolares **`-7`**.

7. **Mapeamento de campos para o Zabbix**
   - **Nome de usuário**: prioriza o **`mobile` (número de celular)**; sem ele, converte o **`name` para pinyin** (ex.: 张三 → `zhangsan`); se a conversão falhar, usa o **`userid`**. Usa a tabela GB2312 embutida; exige `iconv` ou `mbstring`.
   - **Nome**: `name`.
   - **E-mail**: `email`, com reserva em `org_email`.
   - **Celular**: `mobile` (exige permissão de leitura; usado como nome de usuário quando presente; caso contrário, usa-se o pinyin do nome).

8. **Ative no Zabbix**  
   **Usuários → Configurações de sincronização** → adicione uma configuração DingTalk → App Key, App Secret, `root_department_id` → salve e ative → pré-visualize/sincronize em **Sincronização de IM**.

##### Exemplo de configuração do DingTalk

```json
{
    "id": "set_dingtalk_prod",
    "name": "DingTalk Produção",
    "provider": "dingtalk",
    "enabled": true,
    "root_department_id": "1",
    "app_key": "dingxxxxxxxxxx",
    "app_secret": "your_app_secret"
}
```

##### Solução de problemas do DingTalk

| Sintoma / erro | Causa provável | Solução |
|-----------------|--------------|-----|
| `88` / `sub_code=60011` sem permissão de API | Falta `qyapi_get_department_list` / `qyapi_get_department_member` | Solicite os escopos em Gerenciamento de permissões; aguarde a aprovação |
| `50004` departamento fora do escopo de permissão | O escopo de contatos do aplicativo não cobre o departamento de destino | Amplie o escopo para a organização raiz ou os departamentos necessários |
| `60003` departamento não encontrado | `root_department_id` errado | Confirme o ID em **Contatos → Departamentos** na administração; a raiz é `1` |
| `40009` ID de departamento inválido | ID de departamento não numérico ou ≤ 0 | Use um inteiro positivo em `root_department_id` |
| `40001` AppKey/AppSecret inválidos | Credenciais erradas ou aplicativo removido | Copie novamente do console do desenvolvedor |
| `43007` autorização necessária | Token inválido ou permissão insuficiente | Confira as credenciais e os escopos concedidos |
| Poucos departamentos/usuários | Raiz errada ou escopo restrito | Use `root_department_id=1` e escopo da organização inteira |
| Aparecem departamentos relacionados a `-7` | Contatos escolares ativados | O módulo exclui automaticamente; nenhuma ação necessária |
| Celular/e-mail vazios | Aplicativo de terceiros ou falta de escopo de informações pessoais | Use um **aplicativo interno** e conceda as permissões de leitura |
| Nome de usuário continua `userid` (esperado pinyin) | `PinyinHelper.php` desatualizado; `name` vazio; falta `iconv`/`mbstring` | Atualize os arquivos do módulo; confirme que a API retorna `name`; verifique as extensões do PHP |
| Pinyin duplicado / falha na criação | Mesmo nome sem celular → mesmo pinyin (ex.: `zhangsan`) | Cadastre o celular no DingTalk ou crie/associe os usuários manualmente no Zabbix |
| API rejeitada | IP do servidor não liberado | Defina o IP de saída em **Gerenciamento de desenvolvimento** |

> Códigos de erro: [Códigos de erro da API de servidor (legado)](https://open.dingtalk.com/document/orgapp/server-api-error-codes-1). Visão geral das operações de departamento: [Operações de departamentos de contatos](https://open.dingtalk.com/document/orgapp/operations-related-to-address-book-departments).

**Comportamento fixo (não configurável no config.json)**

- Os nomes dos grupos de usuários não têm prefixo; correspondem aos nomes dos departamentos no IM.
- A correspondência de usuários é fixa pelo **nome de usuário** (WeCom/Feishu: ID da conta no IM; **DingTalk: celular primeiro**, depois o pinyin do nome e por fim o `userid`).

### Uso

1. Abra **Usuários → Configurações de sincronização** e adicione/ative uma configuração de sincronização (WeCom / Feishu / DingTalk).
2. Abra **Usuários → Assistente de sincronização de IM → Sincronização de IM**; a configuração ativa aparece no topo (use o botão **Configurações de sincronização** para ir à página de gerenciamento).
3. Clique em **Pré-visualizar departamentos** para revisar o resultado da correspondência.
4. Clique em **Sincronizar todos os departamentos** para sincronizar os departamentos do IM com os grupos de usuários do Zabbix.
5. Clique em **Sincronizar todos os usuários** para criar/associar os membros do IM como usuários do Zabbix.

Ordem recomendada: **configurar e ativar a configuração de sincronização → pré-visualizar departamentos → sincronizar todos os departamentos → sincronizar todos os usuários**.

## Licença

Este projeto segue a licença do Zabbix. Para mais detalhes, consulte a [Licença do Zabbix](https://www.zabbix.com/license).
