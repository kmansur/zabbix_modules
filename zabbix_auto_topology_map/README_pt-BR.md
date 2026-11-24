# Módulo Zabbix Auto Topology Map

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Este módulo de frontend do Zabbix descobre automaticamente as conexões entre equipamentos de rede e oferece uma interface central para visualizar e editar mapas de topologia. Ele adiciona **Mapa de topologia automático** ao menu **Monitoramento** da interface web do Zabbix e lê as informações de vizinhança LLDP, CDP e EDP via SNMP para gerar mapas da topologia de rede.

O módulo lê e grava os mapas pelas APIs nativas do Zabbix e compartilha os mesmos dados com **Monitoramento → Mapas**. A tela mostra o status dos hosts, informações de incidentes e as triggers dos enlaces. Largura de banda, tráfego de entrada e tráfego de saída podem ser exibidos associando manualmente itens de monitoramento aos enlaces.

## Recursos

- **Gerenciamento de mapas**: crie, pesquise por nome, ordene, renomeie, exclua em lote e exporte mapas em JSON.
- **Descoberta automática**:
  - Lê os vizinhos LLDP / CDP / EDP a partir de hosts com interface SNMP.
  - Profundidade de descoberta configurável de 1 a 10 níveis, com padrão 3.
  - Opcionalmente inclui dispositivos não gerenciados e escolhe ícones automaticamente pelo tipo de dispositivo.
  - Gera mapas em layout de árvore, com suporte a várias conexões de porta entre o mesmo par de dispositivos.
- **Redescoberta**: atualiza dispositivos e enlaces do mapa atual, reaproveitando elementos e enlaces existentes sempre que possível para preservar as métricas e o roteamento dos enlaces. Reorganize o layout inteiro ou mantenha as coordenadas atuais.
- **Edição na tela**: adicione, arraste e remova elementos do mapa, formas e enlaces. Edite elementos de imagem e de host, além de retângulos, elipses e linhas.
- **Métricas dos enlaces**: selecione vários itens numéricos, separadamente, para largura de banda, tráfego de entrada e tráfego de saída. Os valores mais recentes são somados e convertidos para as unidades adequadas.
- **Status dos enlaces**: associa automaticamente triggers de status de porta ou permite escolhê-las manualmente. Quando uma trigger indica incidente, o enlace membro correspondente muda de cor.
- **Roteamento dos enlaces**: curvas automáticas, roteamento ortogonal automático que contorna obstáculos e pontos de inflexão manuais. Enlaces agregados compartilham rótulos e configurações de roteamento.
- **Status dos hosts**: mostra os estados normal, incidente, manutenção e desativado, com destaque de incidentes e ícones de status.
- **Expansão de macros**: o texto das formas e os rótulos dos elementos de host suportam `{MAP.NAME}`, `{HOST.NAME}` e `{HOST.CONN}`, expandidos na renderização a partir do nome do mapa atual e do nome/endereço de conexão mais recentes do host.
- **Atualização automática**: atualiza status e métricas a cada 60 segundos no modo de pré-visualização. A atualização é pausada durante a edição ou quando a página está oculta.
- **Menu nativo de host**: clique em um host no modo de pré-visualização para abrir o menu de host nativo do Zabbix e acessar incidentes, dados recentes, gráficos e outras páginas.
- **Persistência da visualização**: lembra a última visualização de lista ou de mapa do usuário e permite zoom, deslocamento e ajuste do mapa à janela.
- **Idiomas e temas**: exibe chinês, inglês, russo ou português do Brasil conforme o idioma do usuário no Zabbix e suporta temas claro e escuro.

## Instalação

### Instalar o módulo

Implante este repositório no diretório de módulos da sua instalação do frontend do Zabbix. Os caminhos mais comuns são:

```bash
# Caminho comum para Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Caminho comum para Zabbix 7.2+ / 7.4 / 8.0 (a compatibilidade precisa ser verificada à parte)
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

Se o repositório já estiver implantado, atualize os arquivos existentes. Outra opção é copiar apenas o diretório `zabbix_auto_topology_map` para o seu diretório de módulos. Garanta que o `manifest.json` fique diretamente dentro desse diretório, sem um nível de diretório a mais. O usuário do servidor web precisa de permissão de leitura nos arquivos do módulo.

### ⚠️ Ajustar o manifest.json

Se você tentar implantar o módulo no Zabbix 6.0, altere o `manifest_version` para `1.0`:

```bash
# Execute a partir da raiz do repositório; esta alteração não significa compatibilidade verificada com o Zabbix 6.0
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_auto_topology_map/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o **Zabbix Auto Topology Map** e ative-o.
4. Atualize a página e abra **Monitoramento → Mapa de topologia automático**.

Também é possível acessar o módulo diretamente em `zabbix.php?action=topomap.view`.

## Observações

- **Permissões**: a visibilidade de mapas e hosts segue as permissões do Zabbix. Criar, editar e excluir mapas também exige a permissão de perfil **Criar e editar mapas** (`actions.edit_maps`).
- **Dados compartilhados**: o módulo compartilha dados com os mapas nativos do Zabbix. Editar ou excluir um mapa por qualquer uma das interfaces afeta a outra. Exporte um backup antes de fazer alterações. O módulo oferece exportação em JSON, mas não uma interface de importação correspondente.
- **Configuração de métricas**: os itens de banda e de tráfego precisam ser selecionados manualmente; não há associação automática. Por padrão, o seletor se limita aos hosts das duas pontas do enlace e mostra apenas itens numéricos. Um valor mais recente ausente é exibido como `-`.
- **Associação de triggers**: o diálogo de descoberta permite editar as palavras-chave de status de porta, uma por linha. A palavra-chave padrão em chinês é “处于关闭状态”; nos demais idiomas, o padrão é `link down`. Ajuste essas palavras-chave aos nomes reais das suas triggers.
- **Redescoberta**: por padrão, dispositivos e enlaces que não forem redescobertos são mantidos. Você pode optar por remover os enlaces automáticos que não forem mais descobertos; enlaces manuais são sempre mantidos. Revise as opções de layout e de retenção antes de atualizar.
- **Desempenho da descoberta**: em redes grandes, comece com poucos hosts-semente e pouca profundidade. Timeouts de SNMP, restrições de acesso ou protocolos de descoberta de vizinhos desativados podem fazer com que dispositivos sejam ignorados ou gerar resultados incompletos.
- **Dimensões da tela**: as dimensões são calculadas automaticamente a partir do conteúdo, e a tela pode se expandir nas quatro direções. Não há entrada manual de largura e altura.
- **Limitações de elementos nativos**: elementos de grupo de hosts, trigger e mapa aninhado podem ser pré-visualizados, arrastados e excluídos, mas suas propriedades precisam ser editadas no editor nativo. Os cálculos de status atualmente se aplicam apenas a elementos de host.
- **Limitações de ícones e macros**: a pré-visualização do módulo não resolve mapeamentos de ícones. O texto das formas e os rótulos dos elementos de host suportam as macros `{MAP.NAME}`, `{HOST.NAME}` e `{HOST.CONN}`, expandidas na renderização a partir do nome do mapa atual e do nome/endereço de conexão mais recentes do host (`{HOST.CONN}` prioriza a interface principal e depois o IP ou DNS da interface SNMP). Outras macros não são expandidas. Use a página de mapas nativa do Zabbix quando precisar da renderização nativa completa.
- **Atualizações e backups**: faça backup dos dados de mapas nativos e das tabelas próprias do módulo para preservar métricas, roteamento e configurações dos enlaces. Após atualizar, use `Ctrl+F5` para limpar o cache do navegador. Se o menu não for atualizado, verifique o cache do PHP OPcache.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O plugin usa o framework de módulos do Zabbix. Estrutura de arquivos:

- `manifest.json`: configuração do módulo, rotas e declaração dos arquivos estáticos
- `Module.php`: registra a entrada de menu Monitoramento → Mapa de topologia automático
- `actions/TopomapView.php`: controlador da página e estado inicial da view
- `actions/TopomapData.php`: endpoints de dados para gerenciamento de mapas, gravação dos resultados da descoberta, métricas e roteamento
- `views/topomap.view.php`: view da página e estrutura dos diálogos
- `services/LldpDiscovery.php`: descoberta de vizinhos via SNMP, interpretação das portas e associação de dispositivos
- `lib/LanguageManager.php`: textos em chinês, inglês, russo e português do Brasil
- `assets/js/topomap.js`: lista, tela, interações de edição, descoberta automática e atualização de status
- `assets/css/topomap.css`: estilos da página e suporte a temas

O conteúdo dos mapas é salvo pela API nativa `map` do Zabbix. Métricas, roteamento dos enlaces e configurações do módulo ficam em tabelas próprias do módulo. Ao estender a lógica de gravação, considere a reutilização de IDs de elementos e enlaces e a limpeza da configuração associada.

Para mais informações, consulte a [documentação de desenvolvimento de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

### Configurar o ambiente de descoberta automática

- Instale e ative a extensão `snmp` no **ambiente PHP usado pelo frontend web do Zabbix**. O módulo não precisa da ferramenta de linha de comando `snmpwalk`.
- Garanta que o servidor do frontend alcance os dispositivos de destino via SNMP e que os protocolos LLDP / CDP / EDP estejam ativos nesses dispositivos.
- Configure as interfaces SNMP e os dados de autenticação nos hosts do Zabbix. O seletor de hosts da descoberta só aceita hosts com interface SNMP.
- O módulo cria sob demanda as tabelas `topomap_link_metrics`, `topomap_link_paths` e `topomap_settings`. A conta de banco usada pelo frontend precisa das permissões de criação, leitura e escrita correspondentes.

Instale a extensão SNMP do PHP com o comando do seu sistema operacional:

```bash
# Debian / Ubuntu
sudo apt update
sudo apt install php-snmp

# Sistemas com dnf (como Rocky Linux / AlmaLinux / Fedora)
sudo dnf install php-snmp

# Sistemas com yum (como versões antigas do CentOS / RHEL)
sudo yum install php-snmp
```

## Licença

Este projeto segue a licença do Zabbix. Consulte a [licença do Zabbix](https://www.zabbix.com/license) para mais detalhes.
