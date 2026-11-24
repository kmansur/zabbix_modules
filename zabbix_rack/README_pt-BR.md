# Módulo Zabbix Rack

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend do Zabbix para visualização de racks do data center e controle da posição dos hosts. Ele adiciona o gerenciamento de racks ao menu Inventário do Zabbix Web, com configuração de salas e racks e atribuição visual dos hosts.

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## Recursos

- **Gerenciamento de salas**:
  - Criar, editar e excluir salas
  - Gerenciar a descrição das salas
  - Atribuir grupos de usuários e usuários do Zabbix às salas (controle de acesso)
- **Controle de acesso**:
  - Salas podem ser vinculadas a grupos de usuários e a usuários individuais do Zabbix
  - Por padrão, a tela de permissões vem com todos os grupos de usuários e usuários selecionados (visível para todos)
  - A visualização de racks mostra apenas as salas/racks que o usuário atual pode acessar
  - Salas sem grupos de usuários nem usuários são visíveis para todos
  - O **Super Admin** vê todas as salas na visualização de racks, independentemente das permissões da sala
- **Configuração de racks (página de gerenciamento)**:
  - Apenas o **Super Admin** acessa a página de configuração e as APIs de salvar/excluir
  - Os demais usuários usam apenas a visualização de racks
- **Gerenciamento de racks**:
  - Criar, editar e excluir racks
  - Configurar a altura do rack (de 1 a 60U)
  - Associar racks a salas
- **Visualização de racks**:
  - Layout vertical de rack de 42U
  - Alternância entre **frente / traseira** (a mesma posição U pode ter equipamentos diferentes em cada lado)
  - Status de ocupação das posições U em tempo real
  - Dicas com informações do host ao passar o mouse
  - Clique em posições U livres para atribuir um host
- **Atribuição de hosts**:
  - Atribua hosts do Zabbix a posições U específicas dos racks
  - Filtro por grupos de hosts
  - Pesquisa por nome do host
  - Detecção de conflito de posição U
- **Pesquisa**:
  - Por nome do rack
  - Por nome do host
  - Localização rápida de hosts
- **Internacionalização**: interface em chinês, inglês, russo e português do Brasil
- **Design responsivo**: adapta-se a diferentes tamanhos de tela

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_rack/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o módulo "Zabbix Rack" e ative-o.
4. Atualize a página. O módulo aparece no menu **Inventário** como o submenu "Gerenciamento de racks".

## Observações

### Permissões de armazenamento e SELinux (correção de 21/11/2026)

A configuração de salas/racks fica em `data/config.json` no **frontend web**. Em sistemas com SELinux em modo enforcing, alterar o proprietário e usar chmod não bastam para tornar um arquivo `usr_t` gravável pelo PHP-FPM. Configure o proprietário como o usuário do pool PHP web (no ambiente de teste, apache) e um rótulo gravável persistente:

```bash
# Frontend do Zabbix 8 testado; ajuste o caminho e o usuário do pool PHP à sua instalação.
dnf install policycoreutils-python-utils
mkdir -p /usr/share/zabbix/ui/modules/zabbix_rack/data
chown -R apache:apache /usr/share/zabbix/ui/modules/zabbix_rack/data
chmod 750 /usr/share/zabbix/ui/modules/zabbix_rack/data
# Se o config.json já existir:
chmod 600 /usr/share/zabbix/ui/modules/zabbix_rack/data/config.json
semanage fcontext -a -t httpd_sys_rw_content_t '/usr/share/zabbix/ui/modules/zabbix_rack/data(/.*)?'
restorecon -RFv /usr/share/zabbix/ui/modules/zabbix_rack/data
```

Se a regra local de fcontext já existir, use `semanage fcontext -m`. Não use chmod 777 nem desative o SELinux. Restrinja os rótulos graváveis ao diretório de dados.

O salvamento agora grava um arquivo temporário e substitui a configuração de forma atômica. Uma falha de gravação mantém o arquivo/cache anterior intacto, registra um erro com orientação de correção e preserva um JSON AJAX válido. As verificações de criar/atualizar/editar/excluir no Chromium foram aprovadas, e as salas temporárias foram removidas. Os testes de falha de armazenamento usam diretórios temporários isolados.

- **Desempenho**: em ambientes grandes, considere limitar a quantidade de resultados das consultas.
- **Precisão dos dados**: as informações exibidas refletem o estado atual do banco de dados do Zabbix.
- **Permissões necessárias**: os usuários precisam das permissões adequadas para acessar a visualização de racks, que é filtrada pelas configurações de cada sala; o **Super Admin** vê todas as salas. A **Configuração de racks** é exclusiva do **Super Admin**.

### Controle de acesso das salas (config.json)

Você pode **opcionalmente** adicionar `user_groups` e `users` (arrays de IDs em texto) a cada sala em `data/config.json`. Quando omitidos, o comportamento é o das versões anteriores e a sala fica visível para todos os usuários:

```json
{
    "id": "room1",
    "name": "Sala 1",
    "description": "Sala de teste",
    "user_groups": ["7"],
    "users": ["1"]
}
```

- `user_groups`: IDs dos grupos de usuários do Zabbix com acesso à sala
- `users`: IDs dos usuários do Zabbix com acesso à sala
- Ambos os campos vazios ou ausentes: visível para todos os usuários
- O acesso é concedido se o usuário corresponder a **um** grupo listado **ou** a um usuário listado
- Salvar com todos os grupos **e** todos os usuários selecionados é normalizado como público (nenhum campo de permissão é gravado)

### Frente / traseira do rack (tags do host)

A posição do host pode ser marcada como frente ou traseira:

| Tag | Valor | Significado |
|-----|-------|---------|
| `rack_side` | `back` | Lado **traseiro**; tag ausente ou qualquer outro valor significa **frente** |

- Use a alternância **Frente / Traseira** na visualização de racks; a mesma posição U pode ter equipamentos diferentes em cada lado
- Atribuições na frente não gravam a tag `rack_side` (compatível com versões anteriores)
- Atribuições na traseira gravam `rack_side=back`

### Importante

1. Excluir uma sala também exclui todas as configurações de racks dessa sala
2. Remover um host de um rack exclui apenas as tags relacionadas ao rack no host; o host em si não é excluído
3. Conflitos de posição U são detectados automaticamente ao atribuir hosts
4. Recomenda-se fazer backup regular do arquivo `data/config.json`

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Estrutura de arquivos:

- `manifest.json`: configuração do módulo
- `Module.php`: registro do menu
- `actions/RackManage.php`: lógica do gerenciamento de racks
- `actions/RackView.php`: lógica da visualização de racks
- `views/rack.manage.php`: view do gerenciamento de racks
- `views/rack.view.php`: view da visualização de racks
- `lib/LanguageManager.php`: gerenciamento de idiomas (inclui o dicionário `pt_BR`)
- `lib/ViewRenderer.php`: utilitários de renderização das views
- `lib/ZabbixVersion.php`: utilitários de compatibilidade entre versões
- `lib/RackConfig.php`: gerenciamento da configuração dos racks
- `lib/RackPermission.php`: controle de acesso das salas (grupos de usuários/usuários do Zabbix)
- `lib/HostRackManager.php`: gerenciamento da associação entre hosts e racks

Para extensões, consulte a [documentação de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

### Tema da interface

A visualização e a configuração de racks usam um tema inspirado no Element Plus, com CSS e JavaScript locais, sem Vue nem CDN externo. Selects nativos, validação de formulários e os seis níveis de alarme são mantidos. Ao atualizar, implante tanto as views quanto os assets. O tema compartilhado está em assets/css/element.css; mensagens e diálogos de confirmação estão em assets/js/element.js.

## Licença

Este projeto segue a licença do Zabbix. Para mais detalhes, consulte a [Licença do Zabbix](https://www.zabbix.com/license).
