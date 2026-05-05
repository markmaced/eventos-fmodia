# Plugin WordPress — Área de Eventos FM Odia

## Contexto

A FM Odia (https://www.fmodia.com.br) precisa de uma área de eventos no site para divulgar shows, festas e atrações da rádio. O plugin será uma peça nova no mesmo workspace `c:\laragon\www\monitorAtividade\WP\plugin\` que já hospeda o `MonitorAtividadeWP`. As convenções (classes estáticas com prefixo, autoload manual via Manager, assets versionados com `filemtime()`, REST namespace próprio) serão reaproveitadas para manter coerência entre os dois plugins.

**Decisões já confirmadas com o usuário:**

- Filtro de localidade: dropdown Estado/Cidade **+** botão "usar minha localização" (GPS opcional com raio em km).
- Layout principal: grade mensal estilo Google Calendar (com pílulas coloridas por categoria).
- Detalhe do evento: **apenas modal** (overlay) — CPT não-público, sem URL individual.
- Features extras: imagem destacada, cor por categoria, status (esgotado/cancelado/adiado), faixa de preço, lineup, classificação indicativa, mapa Google embed, botão "Adicionar ao Google Calendar / iCal", suporte a eventos de múltiplos dias (festivais) e botões de compartilhamento social.

---

## Arquitetura

### Nome e estrutura

Plugin: **`FmodiaEventosWP`** (mesma raiz do plugin existente).

```
WP/plugin/FmodiaEventosWP/
├── FmodiaEventosWP.php                    # entry point (registra hooks de ativação/init)
├── class/
│   ├── FmodiaEventosWPManager.php         # init() + registerAssets() + autoload manual
│   ├── FmodiaEventosWPCPT.php             # registra CPT 'fm_evento' + tax 'fm_evento_categoria'
│   ├── FmodiaEventosWPMetaFields.php      # meta boxes admin + save_post + sanitização
│   ├── FmodiaEventosWPCategoryColor.php   # term_meta cor da categoria + UI no admin
│   ├── FmodiaEventosWPApi.php             # REST namespace 'fmodia-eventos/v1'
│   ├── FmodiaEventosWPShortcode.php       # shortcode [fmodia_eventos]
│   └── FmodiaEventosWPIcsBuilder.php      # gera arquivo .ics on-the-fly
├── shortcodes/
│   └── calendario.php                     # template do shortcode (HTML base)
├── admin/
│   ├── meta-box-detalhes.php              # template campos: data, hora, preço, lineup, status
│   ├── meta-box-localizacao.php           # template: endereço, cidade, estado, lat/lng
│   └── term-color-field.php               # template do campo cor na edição da taxonomia
├── css/
│   ├── calendario.css                     # frontend (grade, modal, filtros)
│   └── admin.css                          # estilo das meta boxes
└── js/
    ├── calendario.js                      # frontend (render grade, modal, filtros, GPS)
    └── admin.js                           # admin (validação datas, geocoder Nominatim opcional)
```

### Padrões herdados do `MonitorAtividadeWP`

- Classes estáticas com prefixo `FmodiaEventosWP*`, sem namespaces.
- `FmodiaEventosWPManager::init()` faz `require_once` condicional de cada classe (guard `$initiated`).
- Constantes `FMODIAEVENTOSWP_PLUGIN_DIR` e `FMODIAEVENTOSWP_PLUGIN_URL` definidas no entry.
- Assets enfileirados só quando o shortcode roda (`render()` chama `wp_enqueue_*`).
- Cache busting com `filemtime()`.
- REST com namespace `fmodia-eventos/v1`.

Referências (usar como modelo de cópia/adaptação):
- [MonitorAtividadeWP.php](WP/plugin/MonitorAtividadeWP/MonitorAtividadeWP.php)
- [class/MonitorAtividadeWPManager.php](WP/plugin/MonitorAtividadeWP/class/MonitorAtividadeWPManager.php)
- [class/MonitorAtividadeWPShortcode.php](WP/plugin/MonitorAtividadeWP/class/MonitorAtividadeWPShortcode.php)
- [class/MonitorAtividadeWPApi.php](WP/plugin/MonitorAtividadeWP/class/MonitorAtividadeWPApi.php)
- [shortcodes/dashboard.php](WP/plugin/MonitorAtividadeWP/shortcodes/dashboard.php)

---

## Modelo de dados

### CPT `fm_evento`

```php
register_post_type('fm_evento', [
    'public' => false,
    'publicly_queryable' => false,
    'show_ui' => true,           // visível no admin
    'show_in_menu' => true,
    'show_in_rest' => false,     // só nossa REST customizada
    'exclude_from_search' => true,
    'has_archive' => false,
    'rewrite' => false,          // sem URL pública (modal-only)
    'supports' => ['title', 'editor', 'thumbnail'],
    'menu_icon' => 'dashicons-calendar-alt',
    'labels' => [...],
]);
```

### Taxonomia `fm_evento_categoria` (hierárquica `false`, comportamento de tags)

- Term meta `cor` (hex color, ex: `#1976D2`) — usado para colorir as pílulas no calendário.
- Campo extra renderizado nos hooks `fm_evento_categoria_add_form_fields` e `fm_evento_categoria_edit_form_fields`.
- Salvo via hooks `created_fm_evento_categoria` e `edited_fm_evento_categoria` (`update_term_meta`).

### Post meta do evento (todos prefixados com `_fm_evento_`)

| Chave | Tipo | Notas |
|---|---|---|
| `_fm_evento_data_inicio` | `DATE` (Y-m-d) | obrigatório |
| `_fm_evento_data_fim` | `DATE` | opcional (festival) |
| `_fm_evento_hora_inicio` | `TIME` (H:i) | opcional |
| `_fm_evento_hora_fim` | `TIME` | opcional |
| `_fm_evento_local_nome` | string | ex: "Vivo Rio" |
| `_fm_evento_endereco` | string | rua, número |
| `_fm_evento_cidade` | string | usado no filtro |
| `_fm_evento_estado` | string (UF) | select de UFs |
| `_fm_evento_cep` | string | opcional |
| `_fm_evento_lat` | float | opcional, para GPS |
| `_fm_evento_lng` | float | opcional, para GPS |
| `_fm_evento_link_ingresso` | URL | botão "Comprar" |
| `_fm_evento_data_inicio_venda` | DATETIME | usado para mostrar "vendas a partir de…" |
| `_fm_evento_preco_min` | float | nullable |
| `_fm_evento_preco_max` | float | nullable |
| `_fm_evento_lineup` | text | uma atração por linha (textarea simples) |
| `_fm_evento_classificacao` | string | enum: livre, 10, 12, 14, 16, 18 |
| `_fm_evento_status` | string | enum: confirmado, esgotado, cancelado, adiado |

Sanitização: `sanitize_text_field`, `floatval`, `esc_url_raw`, e validação de enum no save (whitelist).

---

## Fluxo de renderização

### Shortcode `[fmodia_eventos]`

Atributos opcionais: `categoria` (slug), `estado`, `cidade`, `mes` (`Y-m`).

`FmodiaEventosWPShortcode::render($atts)`:
1. `wp_enqueue_style/script` dos assets de frontend.
2. `wp_localize_script` injeta `FMODIA_EVENTOS = { restUrl, restNonce, defaultMonth, ufs[], categorias[] }`.
3. `ob_start()` + `require shortcodes/calendario.php`.

Template `calendario.php` produz o HTML base (sem dados de eventos):

- Header: nome do mês, botões `«` `Hoje` `»`.
- Toolbar de filtros: select Estado, select Cidade (dependente), select Categoria, botão "📍 Minha localização" + slider de raio.
- Container `<div data-fm-eventos-grid>` com 7×6 células vazias (geradas via JS).
- Container `<div data-fm-eventos-modal hidden>` (modal pré-montado, conteúdo preenchido via JS).

### REST API

Namespace: `fmodia-eventos/v1`.

- `GET /eventos?mes=YYYY-MM&estado=&cidade=&categoria=&lat=&lng=&raio=` — retorna eventos do mês com todos os campos necessários ao calendário (id, título, datas, hora, local, cidade/UF, status, cor da categoria, thumb URL).
- `GET /eventos/{id}` — retorna detalhes completos (descrição, lineup, preços, link ingresso, lat/lng, embed do mapa, etc.) para popular o modal.
- `GET /eventos/{id}/ics` — gera arquivo `.ics` (Content-Type `text/calendar`) usando `FmodiaEventosWPIcsBuilder`.
- `GET /localidades` — devolve mapa `{ "RJ": ["Rio de Janeiro", "Niterói"], "SP": [...] }` com cidades distintas dos eventos (para popular os selects).

Permissão: leitura pública (`__return_true`). Cache: header `Cache-Control: max-age=300` para reduzir carga.

### JavaScript do frontend (`js/calendario.js`)

- IIFE/módulo, vanilla JS (sem jQuery), seguindo o padrão do `dashboard.js` existente.
- Estado: `{ currentMonth, filtros, eventos: [] }`.
- Ao carregar: fetch `/localidades` (popula selects) + fetch `/eventos?mes=…`.
- Render da grade: distribui eventos pelos dias (considerando ranges para festivais — pílula aparece em cada dia do range).
- Pílula: `<button data-evento-id="...">` com cor de fundo igual à `cor` da categoria, ícone se status `esgotado` (badge ⚠).
- Click na pílula: fetch `/eventos/{id}` → preenche modal → abre overlay.
- Modal:
  - Banner com `thumbnail`.
  - Título + badge categoria (cor).
  - Badge de status quando ≠ confirmado.
  - Data formatada em pt-BR (`Intl.DateTimeFormat`).
  - Local (nome + endereço) com iframe `https://www.google.com/maps?q={enderecoEncoded}&output=embed` (sem API key).
  - Faixa de preço, classificação, descrição (HTML do `post_content`), lineup (lista).
  - Botão "Comprar Ingresso" (se status `confirmado`).
  - Botão "Adicionar ao Google Calendar" (URL `https://calendar.google.com/calendar/render?action=TEMPLATE&text=&dates=&details=&location=`).
  - Botão "Baixar .ics" (link para `/eventos/{id}/ics`).
  - Botões compartilhar: WhatsApp (`https://wa.me/?text=...`), Facebook (`sharer.php?u=`), X (`twitter.com/intent/tweet`).
- Filtros:
  - Selects: re-fetch ao trocar.
  - GPS: `navigator.geolocation.getCurrentPosition` → manda `lat/lng/raio` na query → backend filtra com Haversine em SQL (`6371 * acos(...)`) ou JS pós-fetch (mais simples para começar).

### Admin (`js/admin.js` + `admin/*.php`)

- Meta box "Detalhes do Evento": data início/fim, hora início/fim, preço min/max, link ingresso, data início venda, classificação (select), status (select), lineup (textarea).
- Meta box "Localização": local nome, endereço, cidade, UF (select com 27 estados), CEP, lat/lng + botão **"Buscar coordenadas"** que chama Nominatim (OpenStreetMap, gratuito, sem API key) com User-Agent identificando o site — fallback manual se falhar.
- Validação client-side: `data_fim >= data_inicio`, `preco_max >= preco_min`.

---

## Verificação (como testar end-to-end)

1. Ativar o plugin em `wp-admin → Plugins`.
2. `wp-admin → Eventos → Categorias`: criar categorias (Show, Festa, Festival) atribuindo cores diferentes.
3. `wp-admin → Eventos → Adicionar novo`:
   - Criar 3 eventos: um simples (1 dia), um festival (3 dias seguidos), um esgotado.
   - Definir cidade/estado e clicar "Buscar coordenadas" para confirmar geocoder.
4. Criar uma página `Eventos` e inserir o shortcode `[fmodia_eventos]`. Confirmar:
   - Grade mensal renderiza com pílulas coloridas conforme categoria.
   - Festival aparece em todos os dias do range.
   - Filtro Estado → Cidade encadeia corretamente.
   - Click na pílula abre modal com banner, descrição, mapa embed, lineup, preço.
   - Botões "Comprar", "Adicionar ao Google Calendar" e "Baixar .ics" funcionam.
   - Status `esgotado` esconde o botão "Comprar".
   - Botão "Minha localização" pede permissão e filtra por raio.
5. Testar em mobile: modal responsivo, filtros usáveis em tela pequena.
6. Verificar logs em `wp-content/uploads/monitor-atividade/logs/` se houver erro REST (reusar o `MonitorAtividadeWPLogger` ou criar um novo `FmodiaEventosWPLogger` simétrico — preferir criar próprio para isolamento).
7. Importar o `.ics` baixado no Google Calendar/Apple Calendar para confirmar que o arquivo é válido.

---

## Sugestões adicionais consideradas, mas fora deste escopo

(Levantadas no diálogo, mantenho registradas caso queira incluir depois.)

- **Galeria de fotos** do local/edições anteriores (campo repeater).
- **Eventos relacionados** ("Você também pode gostar") com base em categoria.
- **Newsletter / aviso de venda** — botão "Avise-me quando começar a venda" com captura de e-mail.
- **Avaliações pós-evento** com controle de exibição apenas após a data fim.
- **Integração com a programação da rádio** (links cruzados com programa que está divulgando o show).
- **Cache** dos endpoints REST com `transient` (5 min) caso o tráfego suba.
- **Importação em massa** via CSV ou via integração com Sympla/Eventbrite.

---

## Critérios de "pronto"

- [ ] CPT, taxonomia e meta fields aparecem no admin com UI funcional.
- [ ] Shortcode renderiza calendário mensal com navegação prev/next/hoje.
- [ ] Filtros (Estado, Cidade, Categoria, GPS+raio) funcionam isoladamente e combinados.
- [ ] Modal abre/fecha sem reload, com todas as seções.
- [ ] `.ics` válido e link "Adicionar ao Google Calendar" abrem corretamente.
- [ ] Convenções do plugin existente respeitadas (prefixo, autoload manual, assets via Manager).
- [ ] Sem erros JS no console; sem warnings PHP no `WP_DEBUG_LOG`.
