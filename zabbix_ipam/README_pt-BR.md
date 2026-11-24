# Módulo Zabbix IPAM

[English](README.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Este módulo de gerenciamento de endereços IP para o frontend do Zabbix centraliza as faixas IPv4, executa varreduras assíncronas de disponibilidade por ICMP e associa automaticamente os endereços aos hosts do Zabbix pelo IP das interfaces. Ele oferece três páginas em **Inventário → IPAM** no Zabbix Web: **Gerenciamento de IPs**, **Detalhes do IP** e **Gerenciamento de tarefas**.

A varredura usa apenas ICMP e não se conecta a portas TCP dos destinos. Faixas grandes são divididas automaticamente em fragmentos e processadas por tarefas PHP CLI em segundo plano, evitando requisições longas no Zabbix Web.

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## Recursos

- **Gerenciamento de faixas de IP**: adicione, edite e exclua faixas em CIDR ou no formato IPv4 início-fim
- **Filtro automático**: pressione Enter em um campo de pesquisa para filtrar; campos de seleção são aplicados automaticamente
- **Varredura de disponibilidade por ICMP**: usa o `fping` para verificar se os endereços estão alcançáveis, sem sondar portas TCP
- **Tarefas assíncronas**: crie tarefas pela interface web e execute-as em segundo plano com PHP CLI
- **Fragmentação e multiprocessamento**: usa fragmentos de 64 endereços por padrão e suporta varreduras paralelas via `pcntl_fork`
- **Progresso ao vivo**: mostra a quantidade de endereços varridos e alcançáveis, de fragmentos concluídos e o status da tarefa
- **Varredura agendada**: o intervalo de varredura é definido no crontab; cada execução varre todas as faixas de IP ativas
- **Matriz de uso de IPs**: mostra a alcançabilidade com células de endereço verdes e cinzas
- **Lista de detalhes dos IPs**: mostra o endereço IP, o nome da faixa, a faixa, o status de alcançabilidade e a associação com host
- **Associação automática de hosts**: associa hosts do Zabbix pelo endereço IPv4 de suas interfaces
- **Links para dados recentes**: abre a página de dados recentes de um host pelo nome na lista ou por um endereço alcançável associado na matriz
- **Paginação**: todas as listas são paginadas e o usuário escolhe a quantidade de linhas por página
- **Internacionalização**: segue automaticamente o idioma do usuário no Zabbix, com inglês e português do Brasil
- **Design responsivo**: adapta-se a telas padrão, largas e estreitas, sem largura máxima fixa
- **Controles de acesso e de entrada**: acesso restrito a administradores do Zabbix e validação de IPv4 e de valores numéricos

## Instalação

### Instalar o módulo

```bash
# Caminho comum de módulos no Zabbix 6.0 / 7.0
cp -a zabbix_ipam /usr/share/zabbix/modules/

# Caminho comum de módulos no Zabbix 7.2+ / 7.4 / 8.0
cp -a zabbix_ipam /usr/share/zabbix/ui/modules/
```

Use o valor de `$ZBX_MODULES_DIR` configurado no seu frontend do Zabbix se o diretório de módulos for diferente destes exemplos.

### ⚠️ Ajustar o manifest.json

```bash
# ⚠️ Execute apenas se o Zabbix 6.0 não reconhecer o manifest_version 2.0
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_ipam/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os módulos recém-instalados.
3. Localize o módulo **IPAM** e ative-o.
4. Atualize a página. O menu **Inventário → IPAM** terá **Gerenciamento de IPs**, **Detalhes do IP** e **Gerenciamento de tarefas**.

## Observações

- **Autorização**: varra apenas redes que você tem autorização para varrer.
- **Limites de endereços**: apenas IPv4 é suportado, com no máximo 65.536 endereços por faixa.
- **Permissões no sistema de arquivos**: os usuários do Zabbix Web e do cron precisam conseguir gravar no diretório `data/` do módulo.
- **Permissões de ICMP**: garanta que o firewall do servidor permite ICMP e que o `fping` executa com sucesso.
- **Execução de comandos**: varreduras manuais pela web dependem do `exec()` do PHP; varreduras pelo cron não dependem de uma requisição web para iniciar o processo em segundo plano.
- **Desempenho**: faixas grandes são processadas em fragmentos, com no máximo quatro processos de trabalho em paralelo por padrão.
- **Associação de hosts**: as associações são derivadas das interfaces de hosts do Zabbix acessíveis ao usuário atual.
- **Armazenamento de dados**: atualmente os dados ficam em arquivos JSON. Faça backup regular do diretório `data/` em produção.

### Solução de problemas

- **A varredura falha logo após clicar em Iniciar varredura**: confirme que o PHP CLI existe, que o `exec()` do PHP está habilitado e que `data/scan_tasks/` é gravável.
- **Todos os endereços aparecem como inacessíveis**: execute o `fping` diretamente como o usuário web ou do cron e verifique o caminho de instalação, a permissão de execução, as regras de firewall e a política de ICMP. O módulo procura `/usr/sbin/fping`, `/usr/bin/fping` e `/sbin/fping`, nessa ordem.
- **Uma tarefa fica pendente**: verifique `data/scan_tasks/<ID da tarefa>.log`, o caminho do PHP CLI e eventuais restrições de SELinux ou AppArmor.
- **O cron não cria tarefas**: confirme que a faixa de IP está ativa e que essa faixa ainda não tem uma tarefa `pending` ou `running`.
- **Não é possível salvar uma faixa de IP**: verifique o proprietário e as permissões de `data/`. O módulo precisa conseguir criar arquivos de lock e arquivos JSON temporários.
- **Um endereço não é associado a um host**: confirme que uma interface de host do Zabbix usa exatamente o mesmo endereço IPv4 e que o usuário atual tem permissão para ver esse host.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Arquivos principais:

- `manifest.json`: metadados do módulo e rotas das páginas
- `Module.php`: registro no menu Inventário
- `actions/IpManager.php`: lógica da lista de faixas de IP
- `actions/IpDetail.php`: detalhes dos IPs, filtros de associação de hosts e paginação
- `actions/IpScan.php`: lógica da lista de tarefas de varredura
- `actions/IpAjax.php`: endpoints AJAX para salvar, excluir, varrer e a matriz de IPs
- `views/ip.manager.php`: página Gerenciamento de IPs
- `views/ip.detail.php`: página Detalhes do IP
- `views/ip.scan.php`: página Gerenciamento de tarefas
- `lib/HostMatcher.php`: associação pelo IP das interfaces dos hosts do Zabbix
- `lib/IpScanner.php`: núcleo de varredura ICMP, fragmentação e multiprocessamento
- `lib/IpStorage.php`: armazenamento de dados em JSON
- `lib/TaskManager.php`: tarefas em segundo plano e gerenciamento das varreduras agendadas
- `lib/LanguageManager.php`: idiomas inglês e português do Brasil
- `lib/ViewRenderer.php`: renderização das views
- `lib/ZabbixVersion.php`: detecção de compatibilidade com a versão do Zabbix
- `assets/`: estilos das páginas e scripts do navegador
- `cli/scan_cron.php`: ponto de entrada da varredura pelo cron

Para detalhes de extensão e desenvolvimento de módulos, consulte a [documentação de desenvolvimento de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

### Como funciona

```text
Página Gerenciamento de IPs / crontab
        ↓
Cria uma tarefa de varredura da faixa de IP
        ↓
Processo PHP CLI em segundo plano
        ↓
Divide em fragmentos de 64 endereços → sondas ICMP com fping
        ↓
Resultados em JSON e progresso da tarefa → matriz de IPs / Detalhes do IP / Gerenciamento de tarefas
```

- Quando o usuário clica em **Iniciar varredura** na interface web, o módulo inicia um processo PHP CLI separado via `exec()` do PHP e `nohup`.
- As varreduras agendadas rodam diretamente por `cli/scan_cron.php`. Cada execução do cron cria tarefas para todas as faixas ativas que ainda não têm uma tarefa pendente ou em execução.
- Quando `pcntl_fork()` está disponível no ambiente CLI, o scanner executa até quatro processos de trabalho em paralelo. Caso contrário, executa automaticamente em série.
- O status e os resultados das varreduras ficam em arquivos JSON. As gravações usam locks de arquivo e substituição atômica com arquivos temporários, para que os processos web e do cron acessem os dados simultaneamente.

### Configurar as permissões dos diretórios

Tanto o usuário do serviço web quanto o usuário do cron precisam ler e gravar em `data/`, `data/scan_tasks/` e `data/results/`. Recomenda-se usar a mesma conta. Se forem contas diferentes, conceda o acesso por um grupo compartilhado. Substitua os caminhos abaixo pelo caminho real de instalação do módulo.

```bash
# Exemplo Debian / Ubuntu
chown -R www-data:www-data zabbix_ipam/data
chmod -R 770 zabbix_ipam/data

# Exemplo RHEL / Rocky Linux
chown -R apache:apache zabbix_ipam/data
chmod -R 770 zabbix_ipam/data
```

### Instalar as dependências da varredura

```bash
# Debian / Ubuntu
apt install fping php-cli php-pcntl

# RHEL / Rocky Linux
dnf install fping php-cli php-process
```

`php-pcntl` ou `php-process` habilitam o multiprocessamento. Sem `pcntl`, o módulo recorre automaticamente à varredura serial em segundo plano. PHP CLI e `fping` são obrigatórios para a varredura.

Após a instalação, verifique o ambiente como o usuário do serviço web:

```bash
# Debian / Ubuntu
sudo -u www-data /usr/bin/php -r 'echo PHP_SAPI, PHP_EOL;'
sudo -u www-data /usr/bin/fping -c 1 -t 350 127.0.0.1

# RHEL / Rocky Linux
sudo -u apache /usr/bin/php -r 'echo PHP_SAPI, PHP_EOL;'
sudo -u apache /usr/sbin/fping -c 1 -t 350 127.0.0.1
```

Para iniciar varreduras manualmente pela interface web, garanta que o PHP não desabilitou o `exec` e que o usuário do serviço web pode executar o PHP CLI. Se a sua política de segurança proíbe o `exec`, você ainda pode usar apenas o ponto de entrada agendado do cron descrito abaixo.

### Varredura agendada

`cli/scan_cron.php` é o único ponto de entrada para varreduras agendadas. A frequência do crontab define o intervalo de varredura. Os exemplos a seguir varrem todas as faixas de IP ativas a cada cinco minutos. Nenhuma tarefa duplicada é criada quando a mesma faixa já tem uma tarefa pendente ou em execução. Não execute varreduras como `root`.

Ao criar `/etc/cron.d/zabbix-ipam`, inclua a conta que executa o comando:

```cron
*/5 * * * * apache /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php >> /var/log/zabbix/ipam-cron.log 2>&1
```

No Debian e no Ubuntu, substitua `apache` por `www-data` na maioria das instalações. Ao usar `crontab -u apache -e` ou `crontab -u www-data -e`, não inclua o nome do usuário novamente na expressão do cron:

```cron
*/5 * * * * /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php >> /var/log/zabbix/ipam-cron.log 2>&1
```

No Zabbix 7.2+, 7.4 e 8.0, o caminho do script costuma ser `/usr/share/zabbix/ui/modules/zabbix_ipam/cli/scan_cron.php`. Garanta que a conta escolhida também consegue gravar no diretório de log. Os arquivos de estado das tarefas e os locks dos workers impedem que a mesma faixa de IP seja enviada mais de uma vez ao mesmo tempo.

#### Testar o ponto de entrada do cron manualmente

```bash
# Escolha apache ou www-data conforme o seu ambiente e ajuste o caminho do módulo
sudo -u apache /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php
```

Nenhuma saída no terminal e uma nova entrada na página Gerenciamento de tarefas normalmente indicam uma execução bem-sucedida. Os erros são gravados na saída de erro padrão.

### Páginas

- **Gerenciamento de IPs**: adicione e mantenha faixas de IP, inicie varreduras, veja as tarefas de varredura e abra a matriz de uso de IPs
- **Detalhes do IP**: filtre todos os endereços por palavra-chave, faixa de IP, status do IP e status de associação com host
- **Gerenciamento de tarefas**: filtre as tarefas de varredura em segundo plano por nome da faixa, status da tarefa ou palavra-chave e veja tarefas pendentes, em execução, concluídas, com falha ou paradas

Clique em **IPs ativos / Total** para abrir a matriz de uso de IPs. Verde indica que o endereço estava alcançável na última varredura ICMP; cinza indica que estava inacessível ou ainda não foi varrido. Se um endereço verde estiver associado a um host do Zabbix, clique nele para abrir a página de dados recentes desse host.

## Licença

Este projeto segue os termos de licença do Zabbix. Consulte a [licença do Zabbix](https://www.zabbix.com/license) para mais detalhes.
