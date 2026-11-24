# Módulo Zabbix Graph Trees

[English](README.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Módulo de frontend do Zabbix que oferece navegação de recursos em árvore e visualização de gráficos de monitoramento. Ele adiciona o menu Árvores de gráficos à seção Monitoramento do Zabbix Web, com navegação em árvore e gráficos em tempo real.

![1](images/1.png)

## Recursos

- **Navegação de recursos em árvore**:
  - Exibição hierárquica dos grupos de hosts
  - Lista de hosts
  - Pesquisa rápida para localizar itens
  - Controle de expandir/recolher
- **Filtro por tags**:
  - Filtra itens por tag e valor da tag
  - Lista suspensa com seleção múltipla para escolher os gráficos com flexibilidade
- **Exibição de gráficos**:
  - Gráficos nativos do Zabbix para gráficos pré-configurados e gerados automaticamente
  - Visualização ampliada em tela cheia
  - Atualização automática (intervalos de 30/60/120/300 segundos)
  - Períodos rápidos de 10 minutos a 30 dias, além de hora inicial e final manuais
  - Um ícone de exportação no canto superior direito de cada gráfico baixa um PNG em alta resolução (área de plotagem de 2400×600)
- **Internacionalização**: interface em inglês e português do Brasil
- **Design responsivo**: adapta-se a diferentes tamanhos de tela
- **Interface moderna**: visual limpo e objetivo

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_graphtrees/manifest.json
```

### Ativar o módulo

1. Acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** para encontrar os novos módulos.
3. Localize o módulo "Zabbix Graph Trees" e ative-o.
4. Atualize a página. O módulo aparece no menu **Monitoramento** como o submenu "Árvores de gráficos".

## Observações

- **Desempenho**: em ambientes grandes, considere limitar a quantidade de resultados das consultas.
- **Precisão dos dados**: as informações exibidas refletem o estado atual do banco de dados do Zabbix.
- **Dependência de itens**: a exibição dos gráficos depende da configuração dos itens correspondentes.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

O módulo usa o framework de módulos do Zabbix. Estrutura de arquivos:

- `manifest.json`: configuração do módulo
- `Module.php`: registro do menu
- `actions/GraphTrees.php`: lógica da árvore de gráficos
- `actions/GraphTreesData.php`: lógica de obtenção de dados
- `views/graphtrees.php`: view da página
- `lib/LanguageManager.php`: gerenciamento de idiomas (inclui o dicionário `pt_BR`)
- `lib/ViewRenderer.php`: utilitários de renderização das views
- `lib/ZabbixVersion.php`: utilitários de compatibilidade entre versões

Para extensões, consulte a [documentação de módulos do Zabbix](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## Configuração do módulo

Selecione grupos de hosts e hosts, filtre os itens por tags e defina o período dos gráficos e o intervalo de atualização automática.

Escolha **Intervalo personalizado** e informe a hora inicial e a final. As alterações são validadas e os gráficos são atualizados automaticamente. Os horários usam o fuso horário do usuário do Zabbix, e o início deve ser anterior ao fim. A grade de gráficos e a visualização ampliada aberta são atualizadas juntas, e as atualizações mantêm o intervalo personalizado aplicado.

Antes de aplicar, o servidor valida o intervalo com o interpretador de tempo nativo do Zabbix e com a configuração de período máximo. Intervalos inválidos, curtos ou longos demais exibem um erro e mantêm o último intervalo aplicado. Mudar para um período pré-definido também impede que uma resposta de validação antiga o sobrescreva.

Clique no ícone de exportação no canto superior direito de qualquer gráfico para gerar e baixar um PNG em alta resolução com o período aplicado. Gráficos pré-configurados e gerados automaticamente podem ser exportados. Os nomes dos arquivos incluem o host, o nome do gráfico e o período.

As exportações verificam a decodificação do PNG e as dimensões reais, rejeitando páginas de login, imagens corrompidas e as pequenas imagens de erro retornadas pelo Zabbix. Consulte o [relatório de testes do ambiente de desenvolvimento 1.1.0](tests/README.md) para os resultados e os scripts de navegador reproduzíveis.

## Licença

Este projeto segue a licença do Zabbix. Para mais detalhes, consulte a [Licença do Zabbix](https://www.zabbix.com/license).
