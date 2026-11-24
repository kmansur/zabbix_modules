# Coleção de módulos para Zabbix

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

<div>
  <img src="images/wechat.jpg" width="20%" alt="wechat">
  <img src="images/gongzhonghao.jpg" width="26%" alt="gongzhonghao">
</div>

## A partir de 15 de novembro de 2026

![Visitas ao projeto](https://zabbix.huoxingxiaoliu.com/api/visits?repository=zabbix_modules)

## Descrição

Esta coleção reúne módulos independentes que ampliam o sistema de monitoramento Zabbix.

## Idioma da interface

Os módulos seguem o idioma do perfil do usuário no Zabbix. Escolha **Português (pt_BR)** no seu perfil para usar os módulos em português do Brasil, ou **Русский** para russo. Se o perfil usar o idioma padrão, os módulos herdam o idioma padrão do sistema Zabbix (`default_lang`). Idiomas sem tradução própria usam o inglês.

## Módulos

![1](images/1.png)

| Nº | Módulo | Versão | O que faz |
| ---: | --- | --- | --- |
| 1 | [Zabbix Reports](./zabbix_reports/README_pt-BR.md) | `1.1.0` | Gera relatórios diários, semanais, mensais e de período personalizado, com pré-visualização, exportação em PDF e envio por e-mail. |
| 2 | [Zabbix CMDB](./zabbix_cmdb/README_pt-BR.md) | `1.1.0` | Reúne em um só lugar o inventário e a configuração dos hosts, com pesquisa por nome do host ou endereço IP e filtro por grupo de hosts. |
| 3 | [Zabbix Graph Trees](./zabbix_graphtrees/README_pt-BR.md) | `1.0.0` | Navega por grupos de hosts e hosts em árvore, filtra itens de monitoramento por tag e exibe gráficos com atualização automática. |
| 4 | [Zabbix Rack](./zabbix_rack/README_pt-BR.md) | `1.1.0` | Gerencia salas de servidores e racks e atribui visualmente os hosts do Zabbix a cada unidade do rack. |
| 5 | [Zabbix SNMP](./zabbix_snmp/README_pt-BR.md) | `1.0.0` | Navega por MIBs, executa SNMP walks, resolve OIDs e cria itens de monitoramento ou templates SNMP em lote a partir dos resultados. |
| 6 | [Zabbix JumpServer](./zabbix_jumpserver/README_pt-BR.md) | `1.0.0` | Sincroniza hosts e grupos de hosts do Zabbix com o JumpServer, cria os nós ausentes e abre conexões com os ativos direto do Zabbix. |
| 7 | [Assistente de sincronização de IM (Zabbix IM)](./zabbix_im/README_pt-BR.md) | `1.0.0` | Sincroniza departamentos e membros do WeCom, Feishu ou DingTalk e associa ou cria usuários no Zabbix, atribuindo-os aos grupos de usuários correspondentes. |
| 8 | [Zabbix Clonehosts](./zabbix_clonehosts/README_pt-BR.md) | `1.3.0` | Clona em lote a configuração de hosts existentes a partir de CSV ou de uma tabela online, com pré-visualização da importação, detecção de conflitos, criação automática de grupos de hosts e acompanhamento do progresso. |
| 9 | [Zabbix IPAM](./zabbix_ipam/README_pt-BR.md) | `1.5.0` | Gerencia faixas IPv4, executa varreduras assíncronas de alcançabilidade por ICMP e associa endereços aos hosts do Zabbix pelo IP da interface. |
| 10 | [Zabbix Switch Visual](./zabbix_switch_visual/README_pt-BR.md) | `1.6.0` | Exibe em um widget de dashboard o chassi do switch, o status das portas RJ45 e SFP, a velocidade dos enlaces, tendências de tráfego e a saúde do equipamento. |
| 11 | [Zabbix Auto Topology Map](./zabbix_auto_topology_map/README_pt-BR.md) | `1.0.37` | Descobre enlaces de rede a partir dos dados de vizinhança LLDP, CDP e EDP via SNMP e gera mapas de topologia editáveis no Zabbix. |
| 12 | [Zabbix Import/Export](./zabbix_export_import/README_pt-BR.md) | `1.3.1` | Transfere hosts, templates de monitoramento e itens de host via CSV ou JSON, com pré-visualização da importação e opções para tratar duplicados. |
| 13 | [Zabbix Rebrand Logo](./zabbix_rebrand_logo/README_pt-BR.md) | `1.1.0` | Substitui os logos da tela de login e da barra lateral e o favicon do navegador, além de personalizar o nome do servidor, o rodapé e o link de ajuda. |
| 14 | [Zabbix Database Export](./zabbix_database_export/README_pt-BR.md) | `3.0.4` | Detecta MySQL, MariaDB ou PostgreSQL, exibe a versão do banco e exporta estrutura e dados para download em SQL ou para um arquivo no servidor. |
| 15 | [Zabbix LLDP Topology](./zabbix_lldp/README_pt-BR.md) | `1.0.0-beta` | Desenha a topologia de vizinhança a partir dos itens de descoberta LLDP do Zabbix, filtra por grupo de hosts ou host e mostra as portas de cada enlace. |
| 16 | [Zabbix Backup and Restore](./zabbix_backup_restore/README_pt-BR.md) | `1.1.0` | Exporta para YAML, por categoria, configurações do Zabbix como hosts, templates, usuários e permissões, e depois pré-visualiza e restaura em uma instância de destino. |

## Resultados dos testes de compatibilidade

| Módulo | Páginas por ambiente | 6.0 | 7.0 | 8.0 | Detalhes |
| --- | ---: | --- |--- | --- | --- |
| `zabbix_assets_export_import` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_assets_export_import/README_en.md) |
| `zabbix_auto_topology_map` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_auto_topology_map/README_pt-BR.md) |
| `zabbix_backup_restore` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_backup_restore/README_pt-BR.md) |
| `zabbix_clonehosts` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_clonehosts/README_pt-BR.md) |
| `zabbix_clouds` | 2 | Aprovado | Aprovado | Aprovado | [README](zabbix_clouds/README_en.md) |
| `zabbix_cmdb` | 3 | Aprovado | Aprovado | Aprovado | [README](zabbix_cmdb/README_pt-BR.md) |
| `zabbix_database_export` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_database_export/README_pt-BR.md) |
| `zabbix_export_import` | 3 | Aprovado | Aprovado | Aprovado | [README](zabbix_export_import/README_pt-BR.md) |
| `zabbix_graphtrees` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_graphtrees/README_pt-BR.md) |
| `zabbix_im` | 2 | Aprovado | Aprovado | Aprovado | [README](zabbix_im/README_pt-BR.md) |
| `zabbix_ipam` | 3 | Aprovado | Aprovado | Aprovado | [README](zabbix_ipam/README_pt-BR.md) |
| `zabbix_jumpserver` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_jumpserver/README_pt-BR.md) |
| `zabbix_lldp` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_lldp/README_pt-BR.md) |
| `zabbix_performance_report` | 2 | Aprovado | Aprovado | Aprovado | [README](zabbix_performance_report/README_en.md) |
| `zabbix_rack` | 2 | Aprovado | Aprovado | Aprovado | [README](zabbix_rack/README_pt-BR.md) |
| `zabbix_rebrand_logo` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_rebrand_logo/README_pt-BR.md) |
| `zabbix_reports` | 4 | Aprovado | Aprovado | Aprovado | [README](zabbix_reports/README_pt-BR.md) |
| `zabbix_rightsizing` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_rightsizing/README_en.md) |
| `zabbix_snmp` | 2 | Aprovado | Aprovado | Aprovado | [README](zabbix_snmp/README_pt-BR.md) |
| `zabbix_switch_backup` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_switch_backup/README_en.md) |
| `zabbix_switch_visual` | 1 | Aprovado | Aprovado | Aprovado | [README](zabbix_switch_visual/README_pt-BR.md) |

## Visão geral dos módulos

### 1. Zabbix Reports

Módulo de frontend do Zabbix para gerar relatórios diários, semanais e mensais, com pré-visualização, exportação em PDF e envio por e-mail.

**Recursos**

- Gera relatórios diários, semanais e mensais.
- Resume a quantidade e o status dos alertas.
- Lista os 10 hosts com mais alertas.
- Lista os 10 hosts com maior uso de CPU e de memória.
- Pré-visualiza os relatórios no frontend.
- Exporta relatórios em PDF manualmente.
- Envia relatórios em HTML por e-mail.

![1](zabbix_reports/images/1.png)
![2](zabbix_reports/images/2.png)

[Documentação](./zabbix_reports/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 2. Zabbix CMDB

Módulo de frontend de banco de dados de gerenciamento de configuração (CMDB) para consultar e gerenciar as informações dos hosts em um só lugar.

**Recursos**

- Pesquisa por nome do host ou endereço IP.
- Filtro por grupo de hosts.
- Exibe nome do host, endereço IP, tipo de interface, total de CPU e de memória, grupos de hosts e outros detalhes.
- Interface em chinês, inglês, russo ou português do Brasil.
- Layout responsivo para diferentes tamanhos de tela.

![1](zabbix_cmdb/images/1.jpg)
![2](zabbix_cmdb/images/2.png)
![3](zabbix_cmdb/images/3.jpg)

[Documentação](./zabbix_cmdb/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 3. Zabbix Graph Trees

Módulo de frontend do Zabbix para navegar pelos dados de monitoramento em uma árvore de grupos de hosts, filtrar itens e ver gráficos ao vivo.

**Recursos**

- Navega por grupos de hosts e hosts em árvore.
- Filtra itens por tag e valor da tag.
- Permite selecionar vários itens em uma lista suspensa para escolher quais gráficos exibir.
- Mostra dados de monitoramento ao vivo em gráficos de linha SVG.
- Sincroniza as dicas (tooltips) entre os gráficos.
- Expande gráficos para tela cheia.
- Atualiza automaticamente a cada 5, 10, 20, 30 ou 60 segundos.
- Períodos de 1 hora a 30 dias.
- Interface em chinês, inglês, russo ou português do Brasil.

![1](zabbix_graphtrees/images/1.png)
![2](zabbix_graphtrees/images/2.png)

[Documentação](./zabbix_graphtrees/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 4. Zabbix Rack

Módulo de frontend do Zabbix para visualizar os racks do data center e controlar onde cada host está instalado. Configure salas de servidores e racks e depois atribua os hosts visualmente.

**Recursos**

- Cria, edita e exclui salas de servidores.
- Cria, edita e exclui racks com altura configurável de 1U a 60U.
- Mostra as unidades ocupadas em um layout vertical de rack de 42U.
- Atribui hosts do Zabbix a unidades específicas do rack.
- Pesquisa por rack ou nome do host.
- Interface em chinês, inglês, russo ou português do Brasil.
- Layout responsivo para diferentes tamanhos de tela.

![1](zabbix_rack/images/1.png)
![2](zabbix_rack/images/2.png)
![3](zabbix_rack/images/3.png)

[Documentação](./zabbix_rack/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 5. Zabbix SNMP

Assistente SNMP para o frontend do Zabbix. Navegue por MIBs, execute SNMP walks, resolva OIDs, crie um item a partir de um resultado ou monte templates SNMP em lote.

**Recursos**

- Varre automaticamente os diretórios de MIBs mais comuns. Mostra em tabela OIDs, OIDs resolvidos, sintaxe, permissões e status, e abre o código-fonte das MIBs.
- Lê automaticamente as configurações de conexão SNMP v1, v2c ou v3 do host e mostra os resultados do walk em uma tabela com paginação no navegador.
- Mostra os resultados brutos e copia comandos `snmpget` ou OIDs.
- Cria um item a partir de um único resultado, com mapeamento automático do tipo de dado.
- Seleciona vários resultados para criar templates e itens SNMP em lote.
- Interface em chinês, inglês, russo ou português do Brasil.

![1](zabbix_snmp/images/1.png)
![2](zabbix_snmp/images/2.png)
![3](zabbix_snmp/images/3.png)

[Documentação](./zabbix_snmp/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 6. Zabbix JumpServer

Módulo de frontend do Zabbix para sincronizar hosts e grupos de hosts com o bastion host JumpServer. Ele envia ativos em lote, cria os nós ausentes e abre a conexão com um host direto do Zabbix.

**Recursos**

- Filtra por grupo de hosts, host ou status de alerta e pesquisa por endereço IP ou nome do host. Por padrão, todos os grupos e hosts são exibidos.
- Envia todos os grupos de hosts ou todos os hosts com um clique e cria automaticamente os nós ausentes no JumpServer.
- O botão **Obter IDs de ativos do JumpServer** associa os ativos do JumpServer pelo endereço IP e grava os IDs de volta no Zabbix como tags.
- Detecta hosts Linux e Windows e cria ou atualiza os ativos no JumpServer. Ao criar um ativo, é possível associar uma conta de login a partir de um template de contas.
- Mostra a quantidade de alertas por severidade e expande a linha para ver os detalhes completos.
- Guarda os IDs de ativos do JumpServer como tags nos hosts do Zabbix. Passe o mouse sobre um host já enviado para reenviá-lo.
- O botão **Conectar** na última coluna abre a página de conexão do host no JumpServer.
- Interface em chinês, inglês, russo ou português do Brasil.

![1](zabbix_jumpserver/images/1.png)

[Documentação](./zabbix_jumpserver/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 7. Assistente de sincronização de IM

Módulo de frontend do Zabbix que sincroniza a estrutura organizacional do **WeCom**, **Feishu** e **DingTalk** com os grupos de usuários do Zabbix. Ele associa os membros a usuários existentes do Zabbix ou cria novos usuários e os adiciona aos grupos correspondentes.

![1](zabbix_im/images/1.png)
![2](zabbix_im/images/2.png)
![3](zabbix_im/images/3.png)

**Recursos**

- Suporte a WeCom, Feishu e DingTalk.
- Gerencia as credenciais de sincronização em **Usuários → Configurações de sincronização**, com uma configuração ativa por vez.
- Cria ou atualiza grupos de usuários do Zabbix por departamento, com opção de usar o caminho completo do departamento como nome.
- Associa ou cria usuários no Zabbix e os adiciona aos grupos correspondentes. Novos usuários recebem senhas de 12 caracteres geradas automaticamente, exibidas uma única vez nos resultados.
- Para usuários do DingTalk sem número de telefone, converte o nome para Pinyin para formar o nome de usuário no Zabbix.
- Pré-visualiza a correspondência de departamentos e usuários antes da sincronização.
- Interface em chinês, inglês, russo ou português do Brasil.

[Documentação em chinês](./zabbix_im/README.md) · [Documentação em inglês](./zabbix_im/README_en.md) · [Documentação em português](./zabbix_im/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 8. Zabbix Clonehosts

Módulo de frontend do Zabbix para criar hosts em lote clonando um host já monitorado. Suporta importação por CSV e preenchimento em tabela no navegador, com pré-visualização, detecção de conflitos, importação seletiva e progresso em tempo real.

**Recursos**

- Usa qualquer host monitorado como origem e herda interfaces, grupos, templates, tags, macros, TLS, IPMI, modo de inventário e outras configurações.
- Faz upload de CSV com detecção automática de UTF-8 ou GBK e modelo para download, ou permite preencher os dados no navegador com edição de linhas, limpeza e validação em tempo real.
- Basta informar o nome do host e o IP da interface. Campos opcionais em branco herdam as configurações do host de origem.
- Cria automaticamente, pela API do Zabbix, os grupos de hosts informados no CSV que ainda não existem e os associa aos novos hosts.
- Pré-visualiza a importação e detecta conflitos de nome de host, campos obrigatórios ausentes, duplicados no mesmo lote e colisões com hosts ou templates existentes. Rótulos de status distinguem itens existentes, itens a criar, itens ausentes e valores herdados do host de origem.
- Permite escolher na pré-visualização quais hosts importar. Hosts em conflito são ignorados automaticamente e os hosts prontos podem ser desmarcados individualmente.
- Volta da pré-visualização para a tabela de edição mantendo os dados informados, para corrigir os hosts que não estão prontos.
- Cria os hosts um a um via AJAX e mostra o progresso em tempo real com contagem de sucessos e falhas.
- Baixa um relatório CSV com nome do host, endereço IP, ID do host, resultado e detalhes de erro.
- Interface em chinês, inglês, russo ou português do Brasil.

![1](zabbix_clonehosts/images/image.png)
![2](zabbix_clonehosts/images/image-1.png)
![3](zabbix_clonehosts/images/image-2.png)
![4](zabbix_clonehosts/images/image-3.png)

[Documentação em chinês](./zabbix_clonehosts/README.md) · [Documentação em inglês](./zabbix_clonehosts/README_en.md) · [Documentação em português](./zabbix_clonehosts/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 9. Zabbix IPAM

Módulo de frontend do Zabbix para gerenciar faixas de endereços IPv4 e executar descoberta assíncrona de hosts por ICMP. Ele associa endereços aos hosts do Zabbix pelo IP da interface.

**Recursos**

- Aceita notação CIDR e endereços IPv4 inicial e final, com até 65.536 endereços por faixa.
- Verifica a alcançabilidade dos hosts por ICMP usando `fping`, sem sondagem de portas TCP.
- Divide redes grandes em lotes e executa as varreduras em segundo plano via PHP CLI, com suporte a multiprocessamento por `pcntl`.
- Inicia varreduras manualmente ou as agenda pelo crontab e mostra o progresso durante a execução.
- Mostra a matriz de uso de IPs, os detalhes dos endereços e as associações com hosts, com filtros e paginação.
- Interface em chinês, inglês, russo ou português do Brasil.

![1](zabbix_ipam/images/1.png)
![2](zabbix_ipam/images/2.png)
![3](zabbix_ipam/images/3.png)

[Documentação em chinês](./zabbix_ipam/README.md) · [Documentação em inglês](./zabbix_ipam/README_en.md) · [Documentação em português](./zabbix_ipam/README_pt-BR.md)

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x

### 10. Zabbix Switch Visual

Widget de dashboard para monitorar portas de switch, que apresenta os itens do Zabbix como o chassi do switch, portas RJ45 e SFP, tendências de tráfego e status do equipamento.

**Recursos**

- Seleciona um único host ou exibe vários switches por grupo de hosts.
- Mostra status, velocidade, tráfego de entrada e saída, erros e alertas de cada porta. Clique em uma porta para abrir os gráficos de histórico.
- Detecta as portas automaticamente e permite configurar membros da pilha, layout das portas, deslocamento do índice SNMP, exclusões e aliases manuais.
- Mostra tendências de tráfego por porta e do switch inteiro, com métricas configuráveis de CPU, memória, temperatura, PoE e ventiladores.
- Personaliza estilo e cores das portas, zoom e a barra de resumo, com interface em chinês, inglês, russo e português do Brasil.

![Painel de portas do Zabbix Switch Visual](zabbix_switch_visual/images/1.png)

[Documentação em chinês](./zabbix_switch_visual/README.md) · [Documentação em inglês](./zabbix_switch_visual/README_en.md) · [Documentação em português](./zabbix_switch_visual/README_pt-BR.md)

**Compatibilidade** — Voltado principalmente ao Zabbix 7.x. Consulte o README do widget sobre adaptação e testes em outras versões.

### 11. Zabbix Auto Topology Map

Módulo de frontend do Zabbix que descobre as conexões de rede a partir dos dados de vizinhança LLDP, CDP e EDP coletados via SNMP. Ele gera mapas de topologia editáveis que compartilham dados com os mapas nativos do Zabbix.

**Recursos**

- Descobre dispositivos e enlaces entre portas automaticamente, com profundidade de 1 a 10 níveis e suporte a dispositivos não gerenciados e a vários enlaces por porta.
- Cria, pesquisa, ordena, renomeia, exclui em lote e exporta mapas em JSON.
- Redescobre a rede, preservando opcionalmente as coordenadas, as métricas dos enlaces e as configurações de roteamento existentes.
- Edita visualmente hosts, imagens, formas e enlaces, com curvas automáticas, roteamento ortogonal que contorna obstáculos e pontos de inflexão manuais.
- Atribui manualmente itens de largura de banda e de tráfego de subida e de descida, com soma entre itens e conversão de unidades.
- Mostra o status e os incidentes dos hosts, associa triggers de status de porta e muda a cor dos enlaces quando há alertas.
- Atualiza status e métricas a cada 60 segundos na pré-visualização, com zoom, deslocamento, ajuste à janela e menus nativos de host.
- Interface em chinês, inglês, russo ou português do Brasil, com tema claro ou escuro.

[Documentação em chinês](./zabbix_auto_topology_map/README.md) · [Documentação em inglês](./zabbix_auto_topology_map/README_en.md) · [Documentação em português](./zabbix_auto_topology_map/README_pt-BR.md)

**Compatibilidade** — Desenvolvido para o Zabbix 7.0 e verificado no 7.0.28. O Zabbix 6.x ainda precisa de testes, e outras versões 7.x e 8.x precisam ser verificadas no ambiente de destino.

### 12. Zabbix Import/Export

Módulo de frontend para migrar hosts, templates de monitoramento e itens de host entre ambientes Zabbix. Acesse em **Inventário → Zabbix Import/Export**.

**Recursos**

- Importa e exporta hosts, templates e itens em CSV ou JSON. A página de templates também aceita importações em JSON nativo do Zabbix.
- Filtra por grupo, seleciona registros em várias páginas e exporta os resultados filtrados. O carimbo de data/hora no nome do arquivo usa o fuso horário do usuário do Zabbix.
- Pré-visualiza as diferenças antes de importar e permite escolher como tratar duplicados. Scripts e outras configurações executáveis são bloqueados por padrão.
- Segue o idioma do usuário do Zabbix, com traduções embutidas em chinês simplificado, inglês, russo e português do Brasil.

![Transferência de hosts no Zabbix Import/Export](zabbix_export_import/images/hosts.jpg)

[Documentação em chinês](./zabbix_export_import/README.md) · [Documentação em inglês](./zabbix_export_import/README_en.md) · [Documentação em português](./zabbix_export_import/README_pt-BR.md)

**Compatibilidade** — Verificado no Zabbix 7.0.32. Há adaptações para 6.0 e 7.4 que precisam ser verificadas no ambiente de destino.

### 13. Zabbix Rebrand Logo

Personaliza a identidade visual do frontend do Zabbix em **Administração → Zabbix Rebrand Logo**.

**Recursos**

- Substitui o logo da tela de login, o logo da barra lateral, o ícone da barra lateral recolhida e o favicon do navegador.
- Pré-visualiza e recorta as imagens na própria página e redimensiona o logo de login na proporção 4:1.
- Personaliza o nome do servidor, o texto do rodapé e o link de Ajuda, com interface em chinês, inglês, russo e português do Brasil.

![Configurações do Zabbix Rebrand Logo](zabbix_rebrand_logo/images/1.png)

[Documentação em chinês](./zabbix_rebrand_logo/README.md) · [Documentação em inglês](./zabbix_rebrand_logo/README_en.md) · [Documentação em português](./zabbix_rebrand_logo/README_pt-BR.md)

**Compatibilidade** — Verificado no Zabbix 7.0 e 8.0.

### 14. Zabbix Database Export

Usuários Super admin do Zabbix podem exportar o banco de dados MySQL, MariaDB ou PostgreSQL do Zabbix como arquivo SQL em **Administração → Exportação do banco de dados**. O módulo detecta o tipo de banco e exibe sua versão.

- Mostra o tamanho estimado das tabelas, filtra por nome e seleciona tabelas em lote.
- Exporta estrutura e dados, somente estrutura ou somente dados. Tabelas não selecionadas mantêm suas instruções de criação. Uma opção exclui os dados das tabelas cujo nome começa com `history`.
- Baixa no navegador ou salva no servidor. Quando o download pelo navegador está selecionado, arquivos SQL acima de 500 MB são salvos automaticamente no diretório informado, que por padrão é `/tmp/zabbix`.
- Interface em chinês, inglês, russo ou português do Brasil. Os nomes de arquivo usam o fuso horário do usuário atual e incluem o tipo de banco.

Os formatos de nome de arquivo são `zabbix_database_backup_mysql_yyyy-mm-dd_hh-mm-ss.sql`, `zabbix_database_backup_mariadb_yyyy-mm-dd_hh-mm-ss.sql` e `zabbix_database_backup_postgresql_yyyy-mm-dd_hh-mm-ss.sql`.

![1](zabbix_database_export/images/1.png)

Suporta Zabbix 7.0 e 8.0, verificado no 7.0.32 e 8.0.0. [Documentação em chinês](./zabbix_database_export/README.md) · [Documentação em inglês](./zabbix_database_export/README_en.md) · [Documentação em português](./zabbix_database_export/README_pt-BR.md)

### 15. Zabbix LLDP Topology

Explore as conexões entre dispositivos e portas usando os dados de vizinhança LLDP que o Zabbix já coleta. Acesse **Monitoramento → Topologia LLDP**.

**Recursos**

- Filtra por grupo de hosts ou host. O módulo lê os valores mais recentes dos itens `lldp.remote.info[*]` dos hosts vinculados ao template `Lldp Discovery`.
- Mostra redes desconectadas separadamente no mapa SVG. Por padrão o mapa exibe três níveis e permite expandir os nós para ver mais.
- Ao passar o mouse sobre um enlace, mostra os dispositivos local e remoto, suas portas e quando os dados foram atualizados pela última vez.

**Compatibilidade** — Zabbix 6.0.x, 7.0.x, 7.4.x e 8.0.x.

[Documentação em português](./zabbix_lldp/README_pt-BR.md) · [English documentation](./zabbix_lldp/README_en.md) · [中文](./zabbix_lldp/README.md)

### 16. Zabbix Backup and Restore

Usuários Super admin do Zabbix podem exportar a configuração por categoria em **Administração → Backup e restauração** e depois pré-visualizá-la e restaurá-la na instância de destino.

**Recursos**

- Exporta hosts, templates, usuários e permissões, ações, períodos de manutenção, serviços e SLAs para um único arquivo YAML. O módulo suporta exportações e uploads de até 500 MiB por arquivo; o limite efetivo de upload depende da configuração do ambiente.
- Pré-visualiza as versões de origem e de destino e a quantidade de registros antes de restaurar. Por padrão, a restauração cria os objetos ausentes; também é possível optar por atualizar os objetos correspondentes.
- Restaura do Zabbix 7.0 para 7.0, de 7.0 para 8.0 ou de 8.0 para 8.0. Se a restauração falhar, todo o lote é revertido.

![Interface de backup e restauração da configuração](zabbix_backup_restore/images/1.png)

[Documentação em português](./zabbix_backup_restore/README_pt-BR.md) · [English documentation](./zabbix_backup_restore/README_en.md) · [中文](./zabbix_backup_restore/README.md)

## Instalação

Os passos a seguir valem para os módulos de frontend comuns. Instale o Zabbix Switch Visual seguindo as instruções próprias dele e depois adicione o widget no modo de edição do dashboard. [Instalação do Zabbix Switch Visual](./zabbix_switch_visual/README_pt-BR.md)

### Método 1. Baixar os pacotes de release para implantação em produção

A página de Releases oferece dois tipos de pacote que podem ser instalados sem Git.

- **Pacote completo** `zabbix_modules-<versão>.tar.gz`, com todos os módulos. O formato da versão é `quantidade_de_módulos.major.minor`, por exemplo `13.2.0`.
- **Pacote de módulo individual** `zabbix_<nome-do-módulo>-<versão>.tar.gz`, com um único módulo.

#### Opção A. Baixar o pacote completo

1. Baixe `zabbix_modules-<versão>.tar.gz`, por exemplo `zabbix_modules-13.2.0.tar.gz`. [Releases](https://github.com/X-Mars/zabbix_modules/releases)
2. Envie-o para o servidor Zabbix e extraia-o no diretório de módulos.

```bash
# Zabbix 6.0 / 7.0
tar -xzf zabbix_modules-<versão>.tar.gz -C /usr/share/zabbix/modules/

# Zabbix 6.4 / 7.2 / 7.4 / 8.0
tar -xzf zabbix_modules-<versão>.tar.gz -C /usr/share/zabbix/ui/modules/
```

3. **No Zabbix 6.0, altere o `manifest_version` dos módulos comuns que suportam o Zabbix 6.0.**

```bash
for mod in /usr/share/zabbix/modules/zabbix_*/; do
  grep -Eq '"type"[[:space:]]*:[[:space:]]*"widget"' "${mod}manifest.json" && continue
  sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' "${mod}manifest.json"
done
```

No Zabbix 6.4, 7.0+ ou 8.0+, mantenha o valor padrão.

#### Opção B. Baixar um módulo individual

1. Baixe o pacote `zabbix_<nome-do-módulo>-<versão>.tar.gz` do módulo desejado. [Releases](https://github.com/X-Mars/zabbix_modules/releases)
2. Envie-o para o servidor Zabbix e extraia-o no diretório de módulos.

```bash
# Zabbix 6.0 / 7.0
tar -xzf zabbix_<nome-do-módulo>-<versão>.tar.gz -C /usr/share/zabbix/modules/

# Zabbix 7.2+ / 7.4 / 8.0
tar -xzf zabbix_<nome-do-módulo>-<versão>.tar.gz -C /usr/share/zabbix/ui/modules/
```

3. **No Zabbix 6.0, altere o `manifest_version`.**

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' /usr/share/zabbix/modules/zabbix_<nome-do-módulo>/manifest.json
```

No Zabbix 6.4, 7.0+ ou 8.0+, mantenha o valor padrão.

### Método 2. Implantar com Git para desenvolvimento ou para acompanhar atualizações

Implante todos os módulos de uma vez.

1. **Zabbix 6.0 / 7.0**

```bash
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/
```

2. **Zabbix 7.2+ / 7.4 / 8.0**

```bash
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

3. **No Zabbix 6.0, altere o `manifest_version`.**

Para atualizar em lote os módulos comuns, ignorando os widgets de dashboard:

```bash
for mod in /usr/share/zabbix/modules/zabbix_*/; do
  grep -Eq '"type"[[:space:]]*:[[:space:]]*"widget"' "${mod}manifest.json" && continue
  sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' "${mod}manifest.json"
done
```

Para atualizar um único módulo, como o Zabbix Reports:

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' /usr/share/zabbix/modules/zabbix_reports/manifest.json
```

No Zabbix 6.4, 7.0+ ou 8.0+, mantenha o valor padrão.

### Ativar os módulos

Depois de implantar os arquivos, ative os módulos na interface web do Zabbix.

1. Abra a interface web do Zabbix.
2. Acesse **Administração → Geral → Módulos**.
3. Clique em **Varrer diretório** para encontrar os novos módulos.
4. Localize e ative os módulos que deseja usar.

![1](images/setting-1.png)
![2](images/setting-2.png)

### Verificar a instalação

Atualize a página e procure os módulos nos menus correspondentes.

- **Relatórios → Relatórios Zabbix** (relatórios diários, semanais e mensais)
- **Inventário → CMDB** (lista de hosts e grupos de hosts)
- **Monitoramento → Árvores de gráficos** (árvore de gráficos)
- **Inventário → Gerenciamento de racks** (gerenciamento de racks)
- **Coleta de dados → Assistente SNMP** (MIBs do Zabbix e Walk do Zabbix)
- **Inventário → JumpServer** (sincronização com o JumpServer)
- **Usuários → Assistente de sincronização de IM** (sincronização e configurações)
- **Coleta de dados → Importação de hosts em lote** (clonar hosts a partir de um host de origem)
- **Inventário → IPAM** (gerenciamento de IPs, detalhes dos endereços e gerenciamento de tarefas)
- **Dashboard → Editar → Adicionar widget → Zabbix Switch Visual** (visualização de portas de switch)
- **Monitoramento → Mapa de topologia automático** (descoberta de rede e edição de topologia)
- **Administração → Exportação do banco de dados** (exportação SQL do banco, para Super admins)

### Instalar um módulo individual

Consulte o README de cada módulo para as instruções de instalação e configuração.

## Contribuição

Relatos de problemas e sugestões são bem-vindos. Abra o relato no diretório do módulo correspondente.

## Licença

Todos os módulos seguem os termos de licença do Zabbix. [Licença do Zabbix](https://www.zabbix.com/license)
