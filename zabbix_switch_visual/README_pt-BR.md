# Zabbix Switch Visual

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

O Zabbix Switch Visual é um widget de dashboard para monitorar portas de switch. Ele monta um chassi de switch visual com portas RJ45/SFP, status, tráfego e informações de resumo a partir dos itens do Zabbix. A interface está disponível em inglês, chinês simplificado, russo e português do Brasil.

![Exemplo do Zabbix Switch Visual em um dashboard](images/1.png)

## Recursos

- **Seleção de hosts:** exibe um único host ou vários switches dos grupos de hosts selecionados.
- **Visualização das portas:** mostra as portas RJ45/SFP, estado do enlace, velocidade, tráfego de entrada/saída, erros e alertas; clique em uma porta para abrir os dados de monitoramento relacionados.
- **Layout das portas:** configure uma quantidade de portas fixa ou detectada automaticamente, membros da pilha, ordem das fileiras, deslocamento do índice SNMP, exclusões e aliases manuais.
- **Tendências e resumo:** mostra minigráficos de tráfego por porta e agregados, além de uptime, modelo, número de série, CPU, memória, temperatura, PoE e ventiladores. As métricas opcionais só aparecem quando seus itens estão configurados.
- **Aparência:** ajuste o estilo e as cores das portas, o zoom e a exibição do resumo.
- **Idiomas:** usa inglês, chinês simplificado, russo ou português do Brasil conforme o idioma do usuário no Zabbix.

## Instalação

### Instalar o módulo

1. Copie o diretório `zabbix_switch_visual` **completo** deste repositório para o diretório `modules` do frontend do Zabbix. O local depende do pacote do frontend; `/usr/share/zabbix/ui/modules/` é um exemplo.

### ⚠️ Ajustar o manifest.json

No 7.0/8.0, mantenha o manifest na versão 2.0 e a entrada do widget de dashboard. A configuração testada no 6.0 usa a página independente `switchvisual.view`. A partir da raiz do repositório, prepare o manifest do frontend de destino antes de varrer o diretório; alterar apenas o `manifest_version` não é suficiente.

```bash
python3 tools/compatibility/prepare.py /usr/share/zabbix
```

### Ativar o módulo

1. No frontend, abra **Administração → Geral → Módulos**, clique em **Varrer diretório** e ative o **Zabbix Switch Visual**.
2. No Zabbix 7.0/8.0, edite um dashboard, adicione o widget **Zabbix Switch Visual**, escolha um host ou grupos de hosts e salve.
3. No Zabbix 6.0, abra a página independente em `zabbix.php?action=switchvisual.view`.

## Observações

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

Mantenha o diretório `zabbix_switch_visual` completo, incluindo `manifest.json`, `Widget.php`, `actions/`, `includes/`, `views/` e `assets/`.

## Desenvolvimento

- `manifest.json`: identidade do widget, actions e arquivos do frontend.
- `Widget.php`, `includes/WidgetForm.php`: nome do widget e campos de configuração.
- `actions/WidgetView.php`, `includes/DataFetcher.php`: obtenção de dados de hosts e itens.
- `views/widget.edit.php`, `views/widget.view.php`: formulário de configuração e visualização das portas.
- `assets/js/class.widget.js`, `assets/css/switch_monitor.css`: interação e estilos do frontend.
- `includes/Translation.php`: textos da interface em inglês, chinês, russo e português do Brasil.

Consulte a [documentação oficial de desenvolvimento de widgets do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules/widgets) para as APIs de widgets.

### Agradecimentos

Agradecemos a Mauro Serafini pelo projeto open source [Zabbix Switch Visual Widget](https://github.com/Mauro-Serafini/Zabbix-Switch-Visual-Widget), usado como referência no desenvolvimento deste módulo.

## Configuração do módulo

### Configuração dos itens

O widget associa os dados das portas por padrões de chave de item. Os padrões são listados abaixo; `*` representa o índice da porta e deve ser ajustado ao seu template.

| Dado | Padrão de chave |
| --- | --- |
| Tráfego de entrada | `ifInOctets[*]` |
| Tráfego de saída | `ifOutOctets[*]` |
| Status da porta | `ifOperStatus[*]` |
| Velocidade da porta | `ifHighSpeed[*]` |
| Erros de entrada | `ifInErrors[*]` |
| Erros de saída | `ifOutErrors[*]` |

A opção "Os itens de banda fornecem bits/s" vem ativada por padrão. Desative-a se os seus itens retornarem bytes por segundo. Contadores cumulativos brutos precisam antes de pré-processamento de taxa no Zabbix. As chaves de PoE, duplex, potência óptica do SFP e itens de sistema são opcionais; deixá-las vazias omite essas métricas.

A detecção automática pode incluir interfaces de VLAN, túnel ou gerência; use as regras de portas excluídas para filtrá-las. Grupos de hosts grandes geram mais consultas à API do Zabbix, por isso valide primeiro os padrões de itens e o layout em um único switch.

## Licença

Consulte os [termos de licença](../README_pt-BR.md#licença) do repositório. Preserve a atribuição a terceiros ao redistribuir.
