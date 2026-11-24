# Módulo Zabbix Reports

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

O Zabbix Reports é um módulo de frontend do Zabbix que gera relatórios diários, semanais e mensais. Ele adiciona o submenu "Relatórios Zabbix" ao menu Relatórios do Zabbix Web, com pré-visualização, exportação em PDF e envio por e-mail.

![1](images/1.png)
![2](images/2.png)

## Recursos

- Tipos de relatório: diário, semanal, mensal e período personalizado
- Conteúdo do relatório:
  - Quantidade de incidentes e estatísticas de status
  - Hosts com mais incidentes (Top 10)
  - Hosts com maior uso de CPU (Top 10)
  - Hosts com maior uso de memória (Top 10)
- Funções:
  - Pré-visualização do relatório na página
  - Exportação manual em PDF
  - Envio do relatório por e-mail (formato HTML)
  - Seleção de intervalo de datas personalizado
- Internacionalização: chinês, inglês, russo e português do Brasil
- Design responsivo: adapta-se a diferentes tamanhos de tela
- Interface moderna: cores em degradê e animações
- Compatibilidade: suporta os templates de Linux Agent e Windows Agent
- Estatísticas: mostra o total de incidentes, incidentes ativos etc.

## Instalação

### Instalar o módulo

```bash
# Para Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Para Zabbix 7.2+ / 7.4 / 8.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Ajustar o manifest.json

Se você usa o Zabbix 6.0, altere o `manifest_version` em `zabbix_reports/manifest.json` para `1.0`:

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
```

### Ativar o módulo

1. Acesse Administração → Geral → Módulos.
2. Clique em **Varrer diretório** para detectar os novos módulos.
3. Localize e ative o módulo "Zabbix Reports".
4. Atualize a página — o módulo aparece em Relatórios → Relatórios Zabbix.

## Observações

- Desempenho: em ambientes grandes, limite o tamanho dos resultados das consultas para melhorar o tempo de resposta.
- Precisão dos dados: as informações exibidas refletem o estado atual do banco de dados do Zabbix.
- Configuração de e-mail: o envio por e-mail depende das configurações de e-mail do Zabbix.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Arquivos principais:

- `manifest.json` — configuração do módulo
- `Module.php` — registro do menu
- `actions/CustomReport.php` — lógica do relatório personalizado
- `actions/DailyReport.php` — lógica do relatório diário
- `views/reports.custom.php` — view do relatório personalizado
- `views/reports.daily.php` — view do relatório diário
- `lib/LanguageManager.php` — internacionalização (inclui o dicionário `pt_BR`)
- `lib/ViewRenderer.php` — utilitários de renderização das views
- `lib/ZabbixVersion.php` — compatibilidade entre versões

Para estender o módulo, consulte a documentação de desenvolvimento de módulos do Zabbix: https://www.zabbix.com/documentation/7.0/en/devel/modules

## Configuração do módulo

Selecione um grupo de hosts e o período no menu do relatório. Gere a pré-visualização e use as ações da página para exportar em PDF ou enviar por e-mail.

Em português do Brasil, as datas são exibidas no formato dd/mm/aaaa. As exportações em PDF em russo ou em português usam o TCPDF com uma fonte Unicode (DejaVu Sans). Quando o TCPDF não está disponível, esses relatórios são baixados como HTML pronto para impressão.

## Licença

Este projeto segue a licença do Zabbix. Consulte: https://www.zabbix.com/license
