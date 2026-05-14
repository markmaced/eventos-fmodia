# Tocando Agora — música e cantor no ar

Anotações de referência sobre como o site **FM O Dia** disponibiliza a faixa
que está tocando ao vivo. O dado já existe e é alimentado pela automação da
rádio — não é preciso ler stream nem fonte externa.

> Documento de referência. Não é código do plugin de eventos; está aqui só
> para consulta futura.

---

## Endpoint pronto para "tocando agora"

```
GET /wp-admin/admin-ajax.php?action=get_live_infos
```

Definido em `wp-content/themes/fmodia-2023/inc/ajax/get_live_infos.php`
(`getLiveInfosAjaxCBF`). Já envia `Access-Control-Allow-Origin: *`, então pode
ser consumido de qualquer origem (app, outro site, etc.). O próprio player
(`wp-content/plugins/PlayerBGWP/js/PlayerBGWP.js`) consome isso via
`getLiveInfos()`.

### Resposta (JSON)

```json
{
  "infos": {
    "Title": "Me leva pra casa / Escrito nas estrelas (Ao vivo)",  // a MÚSICA
    "Subtitle": "Lauana Prado",                                     // o CANTOR
    "EventType": "Song",
    "DHStart": "12/05/2025 14:14:23",
    "DHEnd": "12/05/2025 14:18:39",
    "TSStart": 1747059263,
    "TSEnd": 1747059519,
    "Duration": "00:04:16.6",
    "PostID": 262096,
    "MusicTotalIbope": "88381576",
    "StreamingOnlineUsers": false,
    "NextCheck": 60000,
    "MusicInfos": { "...": "capa, mp3, poster, etc. via PlayerBGWPManager" }
  },
  "program": {
    "Title": "...",      // programa / locução no ar
    "Subtitle": "...",
    "Art": "..."
  }
}
```

### Campos principais

| Campo               | Significado                                                        |
|---------------------|--------------------------------------------------------------------|
| `infos.Title`       | Nome da música (campo `Titulo` do Informa)                         |
| `infos.Subtitle`    | Cantor / intérprete (campo `Interprete`/`Artist` do Informa)       |
| `infos.EventType`   | `"Song"` quando é música; pode ser vinheta, fala do locutor, etc. |
| `infos.DHStart/End` | Início/fim da execução (`d/m/Y H:i:s`)                             |
| `infos.TSStart/End` | Mesmos horários em timestamp                                       |
| `infos.Duration`    | Duração da faixa                                                   |
| `infos.PostID`      | ID do post `MusicProgramming` correspondente                       |
| `infos.MusicInfos`  | Infos ricas do áudio (capa, mp3, poster) via `PlayerBGWPManager`   |
| `program`           | Programa/locução atualmente no ar                                  |

---

## Como funciona por trás

1. A automação da rádio (o **"Informa" / "InfoAudio"**) faz `POST` em
   `?action=update_live_infos`
   (`wp-content/themes/fmodia-2023/inc/ajax/update_live_infos.php`) com a
   programação do canal.
2. Esse handler:
   - Cria/atualiza posts `MusicProgramming` (um por faixa — título = música,
     excerpt = intérprete).
   - Salva o item atual numa **página** `Tocando Agora`
     (`post_name` = `agora-na-programacao`, guid `/{CHILD_TITLE_SLUG}/liveinfos`),
     com o JSON da faixa atual no `post_content`.
   - Grava também um arquivo `wp-content/uploads/live/{canal}.json`.
3. O `get_live_infos` lê essa página `liveinfos`, valida se a faixa ainda está
   "no ar" (`TSEnd > agora`) e devolve o que está tocando + o programa.

---

## Pontos de atenção

- **`infos` vem `false`** se o `TSEnd` da última faixa já passou (nada "atual"
  registrado). Em ambiente local/dev normalmente está vazio ou desatualizado —
  quem alimenta isso é o sistema da emissora ao vivo.
- **Sempre cheque `EventType`**: para "música + cantor" use
  `EventType === "Song"`. Outros tipos são vinhetas, falas, etc.
- O `Title` às vezes traz **pot-pourri / medley** com ` / ` separando músicas.
- O endpoint depende de constantes do tema (`CHILD_TITLE_SLUG`) — é por canal
  no setup multi-child.
- Há também um shortcode no tema
  (`wp-content/themes/fmodia-2023/inc/shortcodes/release_list_home.php`) que
  imprime `<input id="musicInfosPlaying" value='...'>` com a música no ar.

---

## Uso rápido (exemplo JS)

```js
fetch('/wp-admin/admin-ajax.php?action=get_live_infos')
  .then(function (r) { return r.json(); })
  .then(function (data) {
    var info = data && data.infos;
    if (info && info.EventType === 'Song') {
      console.log('Música:', info.Title);
      console.log('Cantor:', info.Subtitle);
    } else {
      console.log('No ar agora não é uma música (ou sem dado).');
    }
  });
```

---

## Arquivos de referência

- `wp-content/themes/fmodia-2023/inc/ajax/get_live_infos.php` — leitura ("tocando agora")
- `wp-content/themes/fmodia-2023/inc/ajax/update_live_infos.php` — ingestão (a automação envia para cá)
- `wp-content/plugins/PlayerBGWP/js/PlayerBGWP.js` — `getLiveInfos()` / `updateLivesInfos()`
- `wp-content/plugins/PlayerBGWP/class/PlayerBGWPManager.php` — `getAudioInfosFromPostID()` / `getAudioInfosFromPost()`
- `wp-content/themes/fmodia-2023/inc/shortcodes/release_list_home.php` — shortcode com `#musicInfosPlaying`
