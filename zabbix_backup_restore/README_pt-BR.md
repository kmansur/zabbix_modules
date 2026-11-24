# Zabbix Backup and Restore

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Os 11 tipos de acesso/compartilhados verificados com exportação, pré-visualização, criação, atualização e reversão reais |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Selecione a configuração no frontend do Zabbix, exporte um único arquivo YAML e depois pré-visualize e restaure no destino. O limite por arquivo é de **500 MiB (524.288.000 bytes)** tanto na exportação quanto no upload. Os caminhos de migração suportados são 7.0 → 7.0, 7.0 → 8.0 e 8.0 → 8.0. O módulo também lê backups gerados por versões anteriores dele mesmo.

![1](images/1.png)

## Recursos

- Exporta categorias de configuração para YAML, até 500 MiB por arquivo.
- Pré-visualiza as versões de origem e destino e a quantidade de registros antes de restaurar.
- Cria objetos ausentes ou atualiza os objetos correspondentes, com reversão do lote em caso de falha.
- Interface em chinês, inglês, russo e português do Brasil, conforme o idioma do usuário no Zabbix.

## Instalação

### Instalar o módulo

1. Crie `modules/zabbix_backup_restore` nos dois frontends do Zabbix (origem e destino) e copie para ele `Module.php`, `manifest.json`, `actions/`, `assets/`, `lib/` e `views/`.

### ⚠️ Ajustar o manifest.json

No Zabbix 6.0, altere a versão do manifest do módulo para 1.0. No 7.0/8.0, mantenha 2.0.

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' /usr/share/zabbix/modules/zabbix_backup_restore/manifest.json
```

### Ativar o módulo

1. Como Super admin, abra **Administração → Geral → Módulos**, varra o diretório e ative o **Zabbix Backup and Restore**.
2. Abra **Administração → Backup e restauração**. O menu e todas as operações de backup exigem acesso de Super admin.

## Observações

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

### Testes de regressão locais

No 7.0/8.0, execute `php tests/regression.php /caminho/para/zabbix/ui` para cobrir arquivos atuais e antigos, o limite de 500 MiB, fusos horários, mapeamento de referências, reversão e novas tentativas. No 6.0, execute `php tests/legacy.php /caminho/para/zabbix` para verificar as regras de importação nativas, as duas categorias estendidas, o mapeamento de permissões de grupos e o ciclo de ida e volta do YAML. Cada suíte usa o interpretador YAML e as constantes da respectiva versão.

## Configuração do módulo

### Exportar e restaurar

1. Na origem, selecione as categorias de configuração. Inclua a configuração relacionada ou confirme que ela já existe no destino. Clique em **Baixar YAML**. O nome do arquivo segue o formato `zabbix_backup_yyyy-mm-dd_hh-mm-ss.yaml`, no fuso horário do usuário atual do Zabbix.
2. No destino, selecione o arquivo `.yaml` ou `.yml` e escolha o modo de restauração. O padrão cria a configuração ausente. A opção de atualização também altera os registros correspondentes por nome ou UUID e pode substituir suas listas aninhadas. Considere exportar a configuração do destino antes de ativar as atualizações.
3. Clique em **Pré-visualizar restauração**. Revise as versões de origem e de destino, as contagens do arquivo e eventuais ações manuais posteriores. A pré-visualização verifica a estrutura e a versão do arquivo. O módulo valida as dependências no destino e grava durante a restauração.
4. Clique em **Restaurar configuração**. O navegador envia o mesmo arquivo novamente. A confirmação fica vinculada ao conteúdo do arquivo e à opção de restauração e expira após uma hora. Pré-visualize novamente se escolher outro arquivo, mudar a opção ou deixar a confirmação expirar.
5. O módulo grava pela API do Zabbix e reconstrói as referências. Uma dependência ausente ou ambígua interrompe a restauração e reverte o lote. Depois, verifique os registros importantes pelo nome ou pelo grupo no frontend de destino.

### Cobertura da migração

| Área | Configuração |
| --- | --- |
| Monitoramento | Hosts, templates, grupos de hosts, grupos de templates, descoberta de rede, períodos de manutenção, proxies, grupos de proxies |
| Visualização | Mapas, imagens, dashboards de templates, dashboards globais no 8.0 |
| Acesso e notificações | Usuários, grupos de usuários, perfis, destinos de notificação, tipos de mídia, ações, scripts |
| Serviços e configuração compartilhada | Serviços e dependências, SLAs, macros globais, expressões regulares globais, correlações de eventos |

O Zabbix 6.0 também oferece **Acesso e notificações** (usuários, grupos de usuários, perfis, tipos de mídia, ações e scripts) e **Serviços e configuração compartilhada** (serviços e dependências, SLAs, macros globais, expressões regulares globais e correlações de eventos). O módulo adapta as permissões antigas de grupos de usuários, os campos de scripts e as constantes de condições de ações. Essas operações foram testadas no servidor de desenvolvimento com 6.0.49. Versões de correção anteriores do 6.0 exigem confirmação das APIs disponíveis.

No 6.0, a categoria Monitoramento inclui grupos de hosts, templates, hosts, imagens e mapas. Use a página de exportação da versão correspondente para recursos posteriores, como grupos de templates e grupos de proxies. Esta rodada de testes de aceitação no 6.0 cobre restauração na mesma versão. Verifique dependências e diferenças de API no destino antes de uma restauração entre versões diferentes.

Itens, triggers, regras e protótipos de descoberta de baixo nível, cenários web, mapas de valores e macros de usuário acompanham seus hosts ou templates. Inclua os templates pai, grupos e proxies referenciados ou garanta que existam no destino. O destino redescobre os objetos gerados por descoberta.

- O backup cobre objetos de configuração. Migre histórico, tendências, eventos e estado de execução pelos fluxos de migração de dados correspondentes.
- Redefina as senhas das contas locais recém-criadas e informe novamente os valores das macros secretas. Prepare no destino os diretórios LDAP/SAML, os métodos de MFA e os módulos de frontend referenciados. Provisione novamente os usuários gerenciados externamente.
- A exportação nativa do Zabbix determina a cobertura de mapas, imagens e dashboards. Verifique a propriedade e o compartilhamento no destino. Backups criados no 8.0 exigem um destino 8.0.
- O YAML pode conter PSKs, credenciais SNMP ou de mídia e configuração executável. Use HTTPS, restrinja o acesso aos arquivos e restaure apenas backups de fontes confiáveis.

### Configurações do servidor para arquivos de 500 MiB

O limite de 500 MiB se aplica ao arquivo YAML. Um upload `multipart/form-data` também contém cabeçalhos e campos do formulário. Permita pelo menos **512 MiB (536.870.912 bytes)** para a requisição HTTP e 500 MiB para o arquivo. Um limite menor em qualquer servidor web, proxy reverso, balanceador de carga ou WAF pode rejeitar a requisição. O módulo mostra o limite calculado a partir das configurações da aplicação e do PHP. Verifique os limites das camadas anteriores separadamente.

O módulo lê e grava o YAML registro a registro. A exportação usa armazenamento temporário antes de enviar o download. Uploads do PHP e o buffer do proxy também podem consumir espaço temporário em disco. Reserve espaço suficiente para requisições simultâneas e verifique o acesso aos diretórios pelos serviços envolvidos. As APIs nativas do Zabbix ainda montam as estruturas de configuração na memória do PHP. O pico de uso pode ser várias vezes o tamanho do YAML e depende da quantidade de objetos, do aninhamento e da concorrência. Meça um backup representativo antes de dimensionar a memória do PHP, o limite do contêiner e o `pm.max_children` do PHP-FPM. Com recursos limitados, migre em lotes menores e restaure na ordem das dependências.

#### PHP / PHP-FPM

Aplique estas configurações ao pool que atende o frontend do Zabbix. Os arquivos de pool normalmente ficam em `/etc/php-fpm.d/` ou `/etc/php/<versão>/fpm/pool.d/`. Confirme as configurações efetivas da SAPI web; a configuração da CLI mostrada por `php -i` pode ser diferente.

```ini
; Adicione ao pool FPM existente do Zabbix
php_value[upload_max_filesize] = 500M
php_value[post_max_size] = 512M
php_value[max_input_time] = 1800
php_value[max_execution_time] = 1800
request_terminate_timeout = 2100s

; Exemplo para um ambiente de teste com RAM suficiente.
; Defina este valor com base no pico medido e na memória disponível por worker.
php_value[memory_limit] = 16G
```

No `php.ini` da SAPI web, use as diretivas normais do PHP diretamente. `request_terminate_timeout` pertence ao pool do FPM. Defina `upload_tmp_dir` se o diretório de upload do PHP precisar de um volume separado. O diretório temporário do sistema do PHP, configurável com `sys_temp_dir`, guarda a exportação temporária. Teste com `php-fpm -t` ou o binário com versão correspondente e recarregue o serviço correto. Em contêineres, verifique também os limites de memória e o armazenamento montado. [Configuração do PHP](https://www.php.net/manual/en/ini.core.php) · [Configuração de pools do PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php)

#### Apache 2.4 + PHP-FPM

Defina `ProxyTimeout` no virtual host do Zabbix. Defina `LimitRequestBody` para o caminho do frontend, ajustando-o à URL.

```apache
# Dentro do virtual host do Zabbix
ProxyTimeout 2100
<Location "/zabbix/">
    LimitRequestBody 536870912
</Location>
```

Se `RequestReadTimeout` estiver definido, o timeout do corpo e a taxa mínima de dados também se aplicam a uploads lentos. Verifique essa camada em caso de HTTP 408. Execute `apachectl configtest` ou `httpd -t` antes de recarregar o Apache. [LimitRequestBody](https://httpd.apache.org/docs/2.4/mod/core.html#limitrequestbody) · [ProxyTimeout](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html#proxytimeout) · [mod_reqtimeout](https://httpd.apache.org/docs/2.4/mod/mod_reqtimeout.html)

#### Nginx

Aplique as diretivas no `server` existente do Zabbix ou na `location` que atende o `zabbix.php`. Use os timeouts de FastCGI quando o Nginx se conecta ao PHP-FPM. Use os timeouts de proxy na `location` com `proxy_pass` existente quando ele encaminha para o Apache ou outro backend HTTP.

```nginx
client_max_body_size 512m;
client_body_timeout 300s;

# Nginx → PHP-FPM
fastcgi_send_timeout 2100s;
fastcgi_read_timeout 2100s;

# Nginx → Apache ou outro backend HTTP
proxy_send_timeout 2100s;
proxy_read_timeout 2100s;
```

Os timeouts de corpo do cliente e de leitura do upstream medem o tempo ocioso entre leituras. Uploads grandes podem usar disco em `client_body_temp_path`. Execute `nginx -t` antes de recarregar o Nginx. [Configurações de corpo da requisição no Nginx](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size) · [Configurações de FastCGI](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_read_timeout) · [Configurações de proxy HTTP](https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_read_timeout)

#### Verificação e solução de problemas

| Sintoma | O que verificar |
| --- | --- |
| HTTP 413 ou erro de tamanho de arquivo | O limite de 500 MiB do módulo, `upload_max_filesize`, `post_max_size` e o limite de requisição em cada proxy |
| `POST Content-Length ... exceeds the limit` | O `post_max_size` efetivo da SAPI web, com folga para o YAML e para a sobrecarga do multipart |
| HTTP 500, `Allowed memory size ... exhausted` ou conexão FastCGI reiniciada | Logs do PHP-FPM, `memory_limit` do pool e limites de memória do host ou do contêiner |
| HTTP 502, 503 ou 504 | Workers do PHP-FPM, logs de erro do Apache/Nginx, limites de execução e timeouts do upstream |
| A pré-visualização funciona e a restauração falha | O arquivo e a opção usados na pré-visualização e, depois, as referências no destino e os erros de validação da API |

Depois de recarregar os serviços, atualize o módulo e confira o limite de upload do PHP exibido na página. Exporte e pré-visualize primeiro um arquivo pequeno. Depois teste um arquivo grande representativo e uma restauração real em um destino isolado. Revise os logs do PHP e do servidor web e registre o tempo decorrido, a memória e o uso de disco temporário.

## Licença

Consulte os [termos de licença](../README_pt-BR.md#licença) do repositório. Preserve a atribuição a terceiros ao redistribuir.
