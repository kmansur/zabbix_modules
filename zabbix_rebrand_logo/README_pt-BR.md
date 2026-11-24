# Zabbix Rebrand Logo

[中文](README.md) · [English](README_en.md) · [Русский](README_ru.md) · **Português (Brasil)**

## ✨ Compatibilidade de versões

| Zabbix | PHP | manifest_version | Resultado |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Reconhecido e ativado; páginas aprovadas |
| 7.0.32 | 8.0.30 | 2.0 | Reconhecido e ativado; páginas aprovadas |
| 8.0.0 | 8.4.27 | 2.0 | Reconhecido e ativado; páginas aprovadas |

## Descrição

Este módulo de frontend do Zabbix permite que usuários Super Admin troquem o logo da tela de login, o logo da barra lateral, o ícone da barra lateral recolhida e o favicon do navegador em **Administração → Zabbix Rebrand Logo**. Também permite definir o nome do servidor, o texto do rodapé e a URL de Ajuda. O módulo usa a configuração de identidade visual do próprio Zabbix, sem alterar arquivos do núcleo.

![Tela de login com logo personalizado](docs/images/02-login-page.png)
![Barra lateral com logo personalizado](docs/images/03-sidebar.png)

## Recursos

- **Pré-visualização e recorte de imagens**: pré-visualize a imagem selecionada na própria página e use controles deslizantes para aplicar zoom e reposicioná-la. Os recortes são exportados como PNG estático.
- **Tamanho do logo de login**: largura e altura mantêm a proporção 4:1. O padrão é 120 × 30 pixels e o máximo é 512 × 128 pixels, sujeito aos limites de upload do servidor. O recorte pode manter até o dobro da resolução de exibição quando a imagem de origem e o tamanho do arquivo permitem.
- **Nome do servidor**: mantenha o padrão do Zabbix, informe um nome personalizado ou oculte o nome. A tela de login usa o padrão do Zabbix.
- **Rodapé e link de Ajuda**: personalize o texto do rodapé e o link de Ajuda do menu do usuário. O link aceita URLs HTTP e HTTPS.
- **Interface em chinês, inglês, russo e português do Brasil**: segue o idioma do usuário no Zabbix e suporta temas claro e escuro.
- **Armazenamento persistente**: as configurações e as cópias das imagens ficam em `local/conf/rebrand/`, para que possam ser preservadas entre atualizações.

| Imagem | Tamanho recomendado | Formatos suportados |
| --- | --- | --- |
| Ícone da barra lateral recolhida | 24 × 24 pixels | PNG, JPG, GIF, ICO |
| Favicon do navegador | 32 × 32 pixels | PNG, JPG, GIF, ICO |
| Logo da barra lateral | 91 × 24 pixels | PNG, JPG, GIF |
| Logo de login | 120 × 30 pixels | PNG, JPG, GIF |

## Instalação

### Instalar o módulo

Execute os comandos a seguir a partir da raiz do repositório. Defina `ZABBIX_ROOT` como o diretório que serve o frontend do Zabbix, como `/usr/share/zabbix` ou `/usr/share/zabbix/ui`. Defina `WEB_USER` e `WEB_GROUP` como o usuário e o grupo do pool do PHP-FPM, como `apache` ou `www-data`.

```bash
ZABBIX_ROOT=/usr/share/zabbix
WEB_USER=apache
WEB_GROUP=apache

sudo cp -a zabbix_rebrand_logo "$ZABBIX_ROOT/modules/"
MODULE_DIR="$ZABBIX_ROOT/modules/zabbix_rebrand_logo"
CONF_DIR="$ZABBIX_ROOT/local/conf"

sudo install -d -o "$WEB_USER" -g "$WEB_GROUP" -m 0755 \
  "$MODULE_DIR/assets/logos" "$CONF_DIR/rebrand"
sudo chgrp "$WEB_GROUP" "$CONF_DIR"
sudo chmod g+w "$CONF_DIR"
```

O PHP-FPM precisa de permissão de escrita no diretório de imagens, em `local/conf/rebrand/` e em `local/conf/` para salvar as imagens e substituir atomicamente o `brand.conf.php`. Mantenha o código do módulo sob a propriedade da conta de implantação. Em sistemas com SELinux, configure os diretórios graváveis com `httpd_sys_rw_content_t` e aplique os rótulos com `restorecon`.

### ⚠️ Ajustar o manifest.json

No Zabbix 6.0, altere a versão do manifest do módulo para 1.0. No 7.0/8.0, mantenha 2.0.

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' /usr/share/zabbix/modules/zabbix_rebrand_logo/manifest.json
```

### Ativar o módulo

1. Entre como Super Admin e acesse **Administração → Geral → Módulos**.
2. Clique em **Varrer diretório** e ative o **Zabbix Rebrand Logo**.
3. Abra **Administração → Zabbix Rebrand Logo**, selecione e pré-visualize ou recorte as imagens e clique em **Atualizar**.

Instale a view de login local na raiz do frontend. Execute o comando abaixo apenas se o arquivo de destino não existir. Se você já usa uma view de login personalizada, verifique a compatibilidade antes de ativar esta opção.

```bash
LOGIN_VIEW="$ZABBIX_ROOT/local/app/views/general.login.php"
if [ ! -e "$LOGIN_VIEW" ] && [ ! -L "$LOGIN_VIEW" ]; then
  sudo install -D -m 0644 "$MODULE_DIR/install/login-view.php" "$LOGIN_VIEW"
fi
```

Envie e salve o favicon no módulo. Depois, faça backup do `favicon.ico` original do frontend e crie um link simbólico para o favicon do módulo. Use os comandos abaixo quando o frontend ainda tiver o favicon original e nenhum backup. Se você já usa um link simbólico personalizado, verifique o destino dele antes de atualizá-lo.

```bash
if [ -f "$MODULE_DIR/assets/logos/favicon.ico" ] \
  && [ -f "$ZABBIX_ROOT/favicon.ico" ] && [ ! -L "$ZABBIX_ROOT/favicon.ico" ] \
  && [ ! -e "$ZABBIX_ROOT/favicon.ico.zabbix-default" ]; then
  sudo mv "$ZABBIX_ROOT/favicon.ico" "$ZABBIX_ROOT/favicon.ico.zabbix-default"
  sudo ln -s "$MODULE_DIR/assets/logos/favicon.ico" "$ZABBIX_ROOT/favicon.ico"
fi
```

## Observações

- Cada arquivo é limitado a 2 MB, sujeito aos limites de upload do PHP. Ao salvar, uma imagem de login com recorte pendente ou um arquivo acima do limite é exportado como PNG dentro do limite.
- Para manter um GIF animado, envie o original dentro do limite de tamanho e não faça recorte. Para o favicon do navegador, recomenda-se ICO.
- Se uma imagem antiga continuar aparecendo após a atualização, faça uma atualização forçada da página. Uma janela anônima ajuda a conferir mudanças no favicon.

### Proprietário do diretório e erros ao salvar

As páginas do módulo só mostram um comando de ajuste de proprietário quando o dono ou o grupo do diretório é diferente da conta que executa o PHP. Com o proprietário correto, nenhum lembrete é exibido. Os erros ao salvar mantêm a mensagem original e acrescentam lembretes sobre o proprietário do diretório e o SELinux (`getenforce`). Os textos seguem o idioma do usuário no Zabbix Web. Nenhum diálogo ou painel de comandos extra é adicionado.

## Desenvolvimento

- `manifest.json` e `Module.php`: declaração do módulo e registro do menu.
- `actions/`: leitura, validação e gravação das configurações e imagens.
- `views/` e `assets/css/`: interface de configuração, pré-visualizações e recorte.
- `install/login-view.php`: integração opcional para o tamanho do logo de login.
- `lib/`: tratamento de idioma (inclui `pt_BR`), tamanhos e nome do servidor.

Execute o teste de regressão do nome do servidor com PHP 8.1 ou superior na linha de comando:

```bash
php zabbix_rebrand_logo/tests/ServerNameOverrideTest.php
```

### Agradecimentos

Agradecemos a [Keberneth](https://github.com/Keberneth) por criar e compartilhar o [módulo Zabbix Branding](https://github.com/Keberneth/Zabbix-Modules/tree/main/Branding) original, no qual este módulo se baseia.

## Configuração do módulo

### Atualização a partir do diretório antigo

1. Faça backup do diretório do módulo antigo, de `local/conf/rebrand/` e de `local/conf/brand.conf.php`. Desative o módulo antigo na lista de módulos.
2. Mova o diretório do módulo antigo (`zabbix_brand_logo`, `zabbix_branding` ou `Branding`) para fora do diretório `modules/` do frontend. Varra o diretório para remover o registro dele.
3. Instale o `zabbix_rebrand_logo`, varra novamente e ative-o. Se alguma configuração ou imagem antiga existir apenas no diretório `assets/logos/` do módulo antigo, copie-a para o diretório correspondente do novo módulo.
4. Abra o módulo e clique em **Atualizar** para regravar as URLs das imagens no `brand.conf.php` com o novo diretório. Se você usa um link simbólico para o favicon, atualize-o também para apontar para o novo diretório.

O ID do módulo é `zabbix-rebrand-logo`. As configurações persistentes continuam em `local/conf/rebrand/`.

## Licença

Consulte os [termos de licença](../README_pt-BR.md#licença) do repositório. Preserve a atribuição a terceiros ao redistribuir.
