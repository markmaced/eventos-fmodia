<?php
/**
 * Seeds FM O Dia inspired test events into the local WordPress install.
 *
 * Run from this plugin directory:
 * C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tools\seed-eventos-fmodia.php
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from CLI.\n");
}

$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'eventos-fmodia.test';

$wpLoad = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wpLoad)) {
    exit("wp-load.php not found.\n");
}

require_once $wpLoad;

if (!post_type_exists('fm_evento')) {
    require_once dirname(__DIR__) . '/FmodiaEventosWP.php';
    if (class_exists('FmodiaEventosWPCPT')) {
        FmodiaEventosWPCPT::register();
    }
}

if (!post_type_exists('fm_evento')) {
    exit("Post type fm_evento is not registered. Activate the plugin first.\n");
}

$sources = [
    'menu' => 'https://www.fmodia.com.br/menu-principal/',
    'maratona' => 'https://www.fmodia.com.br/home-maratona/',
    'fm_apresenta' => 'https://www.fmodia.com.br/holofote/ferrugem-e-sorriso-maroto-sobem-ao-palco-do-vivo-rio-em-mais-uma-edicao-do-fm-o-dia-apresenta/?posttyperedir=holofote',
    'suel' => 'https://www.fmodia.com.br/holofote/suel-resgata-essencia-dos-anos-2000-em-novo-audiovisual/?posttyperedir=holofote',
    'xama' => 'https://www.fmodia.com.br/holofote/xama-anuncia-show-gratuito-em-campo-grande-para-abrir-turne-nacional-fragmentado/?posttyperedir=holofote',
    'renan' => 'https://www.fmodia.com.br/holofote/com-participacoes-de-mumuzinho-e-convidados-renan-oliveira-leva-os-pagodes-que-a-gente-gosta-a-quadra-da-portela/?posttyperedir=holofote',
    'ivete' => 'https://www.fmodia.com.br/holofote/o-rio-vai-clarear-ivete-sangalo-confirma-data-da-turne-de-samba-na-cidade/?posttyperedir=holofote',
    'marvvila' => 'https://www.fmodia.com.br/holofote/marvvila-e-confirmada-no-rock-in-rio-2026/?posttyperedir=holofote',
    'respeita4' => 'https://www.fmodia.com.br/holofote/fm-o-dia-lanca-o-audiovisual-respeita-minha-historia-4-canta-revela/?posttyperedir=holofote',
    'dominguinho' => 'https://www.fmodia.com.br/holofote/projeto-dominguinho-ganha-navio-tematico-com-joao-gomes-jota-pe-e-mestrinho/?posttyperedir=holofote',
    'consumidor' => 'https://tudoradio.com/noticias/ver/32817-fm-o-dia-e-a-radio-oficial-do-show-do-dia-do-consumidor-no-rio-de-janeiro',
];

$categories = [
    'FM O Dia Apresenta' => '#1a73e8',
    'Maratona da Alegria' => '#e31b23',
    'Festival da Alegria' => '#f9ab00',
    'Respeita Minha Historia' => '#8e24aa',
    'FM O Dia A Vontade' => '#00acc1',
    'Pagode' => '#188038',
    'Samba' => '#0b8043',
    'Funk' => '#f4511e',
    'Gratuito' => '#34a853',
    'Especial' => '#5f6368',
];

$venues = [
    'vivo_rio' => [
        'name' => 'Vivo Rio',
        'address' => 'Av. Infante Dom Henrique, 85 - Parque do Flamengo',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '20021-140',
        'lat' => '-22.9252',
        'lng' => '-43.1714',
    ],
    'apoteose' => [
        'name' => 'Praca da Apoteose',
        'address' => 'R. Marques de Sapucai, 36 - Santo Cristo',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '20220-007',
        'lat' => '-22.9123',
        'lng' => '-43.1964',
    ],
    'qualistage' => [
        'name' => 'Qualistage',
        'address' => 'Av. Ayrton Senna, 3000 - Barra da Tijuca',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '22775-003',
        'lat' => '-22.9793',
        'lng' => '-43.3655',
    ],
    'fundicao' => [
        'name' => 'Fundicao Progresso',
        'address' => 'R. dos Arcos, 24 - Lapa',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '20230-060',
        'lat' => '-22.9139',
        'lng' => '-43.1804',
    ],
    'portela' => [
        'name' => 'Quadra da Portela',
        'address' => 'R. Clara Nunes, 81 - Madureira',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '21351-110',
        'lat' => '-22.8723',
        'lng' => '-43.3437',
    ],
    'shopping_nova_iguacu' => [
        'name' => 'Shopping Nova Iguacu',
        'address' => 'Av. Abilio Augusto Tavora, 1111 - Luz',
        'city' => 'Nova Iguacu',
        'state' => 'RJ',
        'cep' => '26260-045',
        'lat' => '-22.7559',
        'lng' => '-43.4508',
    ],
    'parque_oeste' => [
        'name' => 'Parque Oeste',
        'address' => 'Campo Grande',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '23000-000',
        'lat' => '-22.8929',
        'lng' => '-43.5578',
    ],
    'feira_sao_cristovao' => [
        'name' => 'Feira de Sao Cristovao',
        'address' => 'Campo de Sao Cristovao, s/n - Sao Cristovao',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '20921-440',
        'lat' => '-22.8977',
        'lng' => '-43.2212',
    ],
    'marina_gloria' => [
        'name' => 'Marina da Gloria',
        'address' => 'Av. Infante Dom Henrique, s/n - Gloria',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '20021-140',
        'lat' => '-22.9196',
        'lng' => '-43.1703',
    ],
    'cidade_rock' => [
        'name' => 'Cidade do Rock',
        'address' => 'Parque Olimpico - Barra da Tijuca',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '22775-039',
        'lat' => '-22.9779',
        'lng' => '-43.3929',
    ],
    'porto_santos' => [
        'name' => 'Porto de Santos',
        'address' => 'Terminal de passageiros - Valongo',
        'city' => 'Santos',
        'state' => 'SP',
        'cep' => '11010-100',
        'lat' => '-23.9580',
        'lng' => '-46.3260',
    ],
    'mane_garrincha' => [
        'name' => 'Estadio Mane Garrincha',
        'address' => 'SRPN - Asa Norte',
        'city' => 'Brasilia',
        'state' => 'DF',
        'cep' => '70070-701',
        'lat' => '-15.7835',
        'lng' => '-47.8992',
    ],
    'estudio_fmodia' => [
        'name' => 'Estudio FM O Dia',
        'address' => 'Rua Carlos Machado, 131 - Barra da Tijuca',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '22775-042',
        'lat' => '-22.9767',
        'lng' => '-43.3715',
    ],
];

$events = [
    ['key' => 'fm-apresenta-sorriso-ferrugem', 'title' => 'FM O Dia Apresenta: Sorriso Maroto + Ferrugem', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-05-09', 'time' => '20:00', 'end_time' => '23:30', 'venue' => 'vivo_rio', 'status' => 'esgotado', 'class' => '18', 'prices' => [90, 260], 'lineup' => ['Sorriso Maroto', 'Ferrugem'], 'source' => 'fm_apresenta', 'ticket' => 'https://bileto.sympla.com.br/event/95934'],
    ['key' => 'maratona-alegria-pagode-sempre', 'title' => 'Maratona da Alegria: Pagode Pra Sempre', 'cat' => 'Maratona da Alegria', 'date' => '2026-05-16', 'time' => '11:00', 'end_time' => '23:59', 'venue' => 'apoteose', 'status' => 'confirmado', 'class' => '16', 'prices' => [80, 300], 'lineup' => ['Menos e Mais', 'Sorriso Maroto', 'Marvvila', 'Jorge Aragao', 'Belo', 'Os Caras da Rua', 'Thiaguinho', 'Ferrugem', 'Ludmilla', 'Dilsinho'], 'source' => 'maratona', 'ticket' => 'https://www.ingresse.com/'],
    ['key' => 'respeita-canta-revela-abertura', 'title' => 'Respeita Minha Historia 4: Canta Revela', 'cat' => 'Respeita Minha Historia', 'date' => '2026-05-20', 'time' => '19:00', 'end_time' => '22:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Clareou', 'Diney', 'Renato da Rocinha', 'Alo Som'], 'source' => 'respeita4'],
    ['key' => 'fmodia-vontade-jorge-aragao', 'title' => 'FM O Dia A Vontade: Jorge Aragao', 'cat' => 'FM O Dia A Vontade', 'date' => '2026-05-24', 'time' => '18:00', 'end_time' => '21:00', 'venue' => 'vivo_rio', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [60, 180], 'lineup' => ['Jorge Aragao'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'festival-alegria-samba-pagode', 'title' => 'Festival da Alegria: Samba e Pagode', 'cat' => 'Festival da Alegria', 'date' => '2026-05-30', 'time' => '16:00', 'end_time' => '23:00', 'venue' => 'qualistage', 'status' => 'confirmado', 'class' => '16', 'prices' => [70, 220], 'lineup' => ['Mumuzinho', 'Dilsinho', 'Pique Novo', 'Caju Pra Baixo'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'suel-volta-2002', 'title' => 'Suel: De Volta Para 2002', 'cat' => 'Gratuito', 'date' => '2026-06-05', 'time' => '18:00', 'end_time' => '20:00', 'venue' => 'shopping_nova_iguacu', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Suel'], 'source' => 'suel'],
    ['key' => 'xama-fragmentado-campo-grande', 'title' => 'Xama: Turne Fragmentado', 'cat' => 'Funk', 'date' => '2026-06-07', 'time' => '19:00', 'end_time' => '21:30', 'venue' => 'parque_oeste', 'status' => 'confirmado', 'class' => '16', 'prices' => [0, 0], 'lineup' => ['Xama'], 'source' => 'xama'],
    ['key' => 'renan-pagodes-portela', 'title' => 'Renan Oliveira: Os Pagodes Que A Gente Gosta', 'cat' => 'Pagode', 'date' => '2026-06-12', 'time' => '20:00', 'end_time' => '23:00', 'venue' => 'portela', 'status' => 'confirmado', 'class' => '16', 'prices' => [50, 140], 'lineup' => ['Renan Oliveira', 'Mumuzinho', 'Thiago Soares', 'Marquinhos Sensacao', 'Fabinho'], 'source' => 'renan', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'ivete-clareou-rio', 'title' => 'Ivete Clareou: Rio de Janeiro', 'cat' => 'Samba', 'date' => '2026-06-14', 'time' => '19:00', 'end_time' => '22:00', 'venue' => 'marina_gloria', 'status' => 'confirmado', 'class' => '16', 'prices' => [120, 420], 'lineup' => ['Ivete Sangalo'], 'source' => 'ivete', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'dia-consumidor-fmodia', 'title' => 'Show do Dia do Consumidor: Radio Oficial FM O Dia', 'cat' => 'Gratuito', 'date' => '2026-06-20', 'time' => '15:00', 'end_time' => '20:00', 'venue' => 'marina_gloria', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Belo', 'Ferrugem', 'MV Bill'], 'source' => 'consumidor'],
    ['key' => 'respeita-bom-gosto-alo-som', 'title' => 'Respeita Minha Historia: Bom Gosto + Alo Som', 'cat' => 'Respeita Minha Historia', 'date' => '2026-06-26', 'time' => '19:30', 'end_time' => '22:30', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Bom Gosto', 'Alo Som', 'Samba da Ladeira'], 'source' => 'respeita4'],
    ['key' => 'fm-apresenta-belo-dilsinho', 'title' => 'FM O Dia Apresenta: Belo + Dilsinho', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-07-03', 'time' => '20:00', 'end_time' => '23:30', 'venue' => 'vivo_rio', 'status' => 'confirmado', 'class' => '18', 'prices' => [100, 280], 'lineup' => ['Belo', 'Dilsinho'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'fmodia-vontade-swing-simpatia', 'title' => 'FM O Dia A Vontade: Swing e Simpatia', 'cat' => 'FM O Dia A Vontade', 'date' => '2026-07-05', 'time' => '17:00', 'end_time' => '20:00', 'venue' => 'fundicao', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [45, 120], 'lineup' => ['Swing e Simpatia'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'festival-funk-autentico', 'title' => 'Festival da Alegria: Funk Autentico', 'cat' => 'Funk', 'date' => '2026-07-10', 'time' => '21:00', 'end_time' => '02:00', 'venue' => 'qualistage', 'status' => 'confirmado', 'class' => '18', 'prices' => [80, 240], 'lineup' => ['Dennis', 'Kevin O Chris', 'DJ Tubarao', 'DJ Anderson Franca'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'respeita-molejo-fundo-quintal', 'title' => 'Respeita Minha Historia: Molejo + Fundo de Quintal', 'cat' => 'Respeita Minha Historia', 'date' => '2026-07-12', 'time' => '18:00', 'end_time' => '21:30', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Molejo', 'Fundo de Quintal', 'Julio Sereno'], 'source' => 'respeita4'],
    ['key' => 'pagode-feira-sao-cristovao', 'title' => 'Pagode da Alegria na Feira', 'cat' => 'Pagode', 'date' => '2026-07-18', 'time' => '16:00', 'end_time' => '22:00', 'venue' => 'feira_sao_cristovao', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [30, 90], 'lineup' => ['Gamadinho', 'Vitinho', 'Guga Nandes'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'samba-lapa-fmodia', 'title' => 'Samba FM O Dia na Lapa', 'cat' => 'Samba', 'date' => '2026-07-25', 'time' => '18:00', 'end_time' => '23:00', 'venue' => 'fundicao', 'status' => 'adiado', 'class' => '16', 'prices' => [40, 110], 'lineup' => ['Arlindinho', 'Salgadinho', 'Billy SP'], 'source' => 'respeita4', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'maratona-esquenta-1', 'title' => 'Esquenta Maratona da Alegria: Menos e Mais', 'cat' => 'Maratona da Alegria', 'date' => '2026-08-01', 'time' => '19:00', 'end_time' => '22:00', 'venue' => 'qualistage', 'status' => 'confirmado', 'class' => '16', 'prices' => [70, 210], 'lineup' => ['Menos e Mais'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'maratona-esquenta-2', 'title' => 'Esquenta Maratona da Alegria: Thiaguinho', 'cat' => 'Maratona da Alegria', 'date' => '2026-08-07', 'time' => '20:00', 'end_time' => '23:00', 'venue' => 'vivo_rio', 'status' => 'esgotado', 'class' => '16', 'prices' => [100, 350], 'lineup' => ['Thiaguinho'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'fmodia-apresenta-ludmilla', 'title' => 'FM O Dia Apresenta: Ludmilla', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-08-09', 'time' => '19:00', 'end_time' => '22:00', 'venue' => 'apoteose', 'status' => 'confirmado', 'class' => '16', 'prices' => [90, 300], 'lineup' => ['Ludmilla'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'respeita-caju-di-proposito', 'title' => 'Respeita Minha Historia: Caju Pra Baixo + Di Proposito', 'cat' => 'Respeita Minha Historia', 'date' => '2026-08-15', 'time' => '18:30', 'end_time' => '21:30', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Caju Pra Baixo', 'Di Proposito', 'Feyjao'], 'source' => 'respeita4'],
    ['key' => 'os-caras-da-rua', 'title' => 'Os Caras da Rua: Ao Vivo FM O Dia', 'cat' => 'Pagode', 'date' => '2026-08-21', 'time' => '20:00', 'end_time' => '23:00', 'venue' => 'portela', 'status' => 'confirmado', 'class' => '16', 'prices' => [50, 150], 'lineup' => ['Caju Pra Baixo', 'Maguzinho', 'Jorginho Faria', 'Yan'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'fmodia-apresenta-pericles-mumuzinho', 'title' => 'FM O Dia Apresenta: Pericles + Mumuzinho', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-08-28', 'time' => '20:00', 'end_time' => '23:30', 'venue' => 'vivo_rio', 'status' => 'confirmado', 'class' => '16', 'prices' => [100, 320], 'lineup' => ['Pericles', 'Mumuzinho'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'marvvila-rock-in-rio', 'title' => 'Marvvila no Rock in Rio 2026', 'cat' => 'Festival da Alegria', 'date' => '2026-09-13', 'time' => '17:00', 'end_time' => '18:30', 'venue' => 'cidade_rock', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Marvvila', 'Suel', 'Dennis'], 'source' => 'marvvila'],
    ['key' => 'festival-alegria-baixada', 'title' => 'Festival da Alegria: Baixada', 'cat' => 'Festival da Alegria', 'date' => '2026-09-18', 'time' => '18:00', 'end_time' => '23:30', 'venue' => 'shopping_nova_iguacu', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Suel', 'Marvvila', 'Renan Oliveira'], 'source' => 'suel'],
    ['key' => 'semana-maluca-dia-1', 'title' => 'Semana Maluca FM O Dia: Abertura', 'cat' => 'Especial', 'date' => '2026-09-21', 'date_end' => '2026-09-23', 'time' => '12:00', 'end_time' => '22:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Equipe FM O Dia', 'Convidados surpresa'], 'source' => 'menu'],
    ['key' => 'respeita-vou-pro-sereno-quinteto', 'title' => 'Respeita Minha Historia: Vou Pro Sereno + Quinteto S.A.', 'cat' => 'Respeita Minha Historia', 'date' => '2026-09-26', 'time' => '18:00', 'end_time' => '21:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Vou Pro Sereno', 'Quinteto S.A.', 'Robinho'], 'source' => 'respeita4'],
    ['key' => 'fm-apresenta-zeca-pagodinho', 'title' => 'FM O Dia Apresenta: Zeca Pagodinho', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-10-02', 'time' => '20:00', 'end_time' => '23:00', 'venue' => 'apoteose', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [100, 360], 'lineup' => ['Zeca Pagodinho'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'viva-samba-jota-moraes', 'title' => 'Viva o Samba: Especial Jota Moraes', 'cat' => 'Samba', 'date' => '2026-10-04', 'time' => '17:00', 'end_time' => '20:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Bruno Cardoso', 'Sergio Jr.', 'Ferrugem', 'Suel', 'Leandro Sapucahy'], 'source' => 'respeita4'],
    ['key' => 'fmodia-vontade-fundo-quintal', 'title' => 'FM O Dia A Vontade: Fundo de Quintal', 'cat' => 'FM O Dia A Vontade', 'date' => '2026-10-09', 'time' => '19:00', 'end_time' => '22:00', 'venue' => 'fundicao', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [60, 160], 'lineup' => ['Fundo de Quintal'], 'source' => 'respeita4', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'festival-alegria-manaus', 'title' => 'Festival da Alegria: Edicao Manaus', 'cat' => 'Festival da Alegria', 'date' => '2026-10-12', 'time' => '17:00', 'end_time' => '23:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['FM O Dia Manaus', 'Convidados da rede'], 'source' => 'menu'],
    ['key' => 'maratona-esquenta-turma-pagode', 'title' => 'Esquenta Maratona: Turma do Pagode', 'cat' => 'Maratona da Alegria', 'date' => '2026-10-17', 'time' => '20:00', 'end_time' => '23:00', 'venue' => 'qualistage', 'status' => 'confirmado', 'class' => '16', 'prices' => [80, 230], 'lineup' => ['Turma do Pagode'], 'source' => 'maratona', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'fmodia-apresenta-pixote-revelacao', 'title' => 'FM O Dia Apresenta: Pixote + Revelacao', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-10-23', 'time' => '20:30', 'end_time' => '23:30', 'venue' => 'vivo_rio', 'status' => 'confirmado', 'class' => '16', 'prices' => [80, 250], 'lineup' => ['Pixote', 'Revelacao'], 'source' => 'respeita4', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'pagode-adame-rdn', 'title' => 'Pagode do Adame + RDN', 'cat' => 'Pagode', 'date' => '2026-10-30', 'time' => '19:00', 'end_time' => '22:30', 'venue' => 'portela', 'status' => 'confirmado', 'class' => '16', 'prices' => [45, 130], 'lineup' => ['Pagode do Adame', 'RDN', 'Entre Elas'], 'source' => 'respeita4', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'maratona-alegria-2026', 'title' => 'Maratona da Alegria 2026', 'cat' => 'Maratona da Alegria', 'date' => '2026-11-08', 'time' => '11:00', 'end_time' => '23:59', 'venue' => 'apoteose', 'status' => 'confirmado', 'class' => '16', 'prices' => [90, 350], 'lineup' => ['Menos e Mais', 'Sorriso Maroto', 'Belo', 'Ferrugem', 'Thiaguinho', 'Dilsinho', 'Marvvila', 'Jorge Aragao'], 'source' => 'maratona', 'ticket' => 'https://www.ingresse.com/'],
    ['key' => 'respeita-netinho-pixote', 'title' => 'Respeita Minha Historia: Netinho de Paula + Pixote', 'cat' => 'Respeita Minha Historia', 'date' => '2026-11-13', 'time' => '19:00', 'end_time' => '22:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Netinho de Paula', 'Pixote', 'Salgadinho'], 'source' => 'respeita4'],
    ['key' => 'fmodia-apresenta-xande-pilares', 'title' => 'FM O Dia Apresenta: Xande de Pilares', 'cat' => 'FM O Dia Apresenta', 'date' => '2026-11-20', 'time' => '20:00', 'end_time' => '23:00', 'venue' => 'vivo_rio', 'status' => 'confirmado', 'class' => '16', 'prices' => [90, 260], 'lineup' => ['Xande de Pilares'], 'source' => 'menu', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'festival-alegria-niteroi', 'title' => 'Festival da Alegria: Niteroi', 'cat' => 'Festival da Alegria', 'date' => '2026-11-28', 'time' => '15:00', 'end_time' => '22:00', 'venue' => 'vivo_rio', 'status' => 'cancelado', 'class' => 'livre', 'prices' => [50, 150], 'lineup' => ['Ferrugem', 'Mumuzinho', 'Os Mulekes'], 'source' => 'menu'],
    ['key' => 'joao-gomes-supercopa', 'title' => 'Joao Gomes: Show de Abertura Supercopa Rei', 'cat' => 'Especial', 'date' => '2026-12-06', 'time' => '14:00', 'end_time' => '15:00', 'venue' => 'mane_garrincha', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Joao Gomes'], 'source' => 'menu'],
    ['key' => 'dominguinho-alto-mar', 'title' => 'Dominguinho em Alto-Mar', 'cat' => 'Especial', 'date' => '2026-12-12', 'date_end' => '2026-12-15', 'time' => '16:00', 'end_time' => '10:00', 'venue' => 'porto_santos', 'status' => 'confirmado', 'class' => '18', 'prices' => [1200, 4800], 'lineup' => ['Joao Gomes', 'Jota.pe', 'Mestrinho'], 'source' => 'dominguinho', 'ticket' => 'https://www.fmodia.com.br/'],
    ['key' => 'especial-fim-ano-fmodia', 'title' => 'Especial FM O Dia: Retrospectiva da Alegria', 'cat' => 'Especial', 'date' => '2026-12-18', 'time' => '18:00', 'end_time' => '22:00', 'venue' => 'estudio_fmodia', 'status' => 'confirmado', 'class' => 'livre', 'prices' => [0, 0], 'lineup' => ['Equipe FM O Dia', 'Artistas convidados'], 'source' => 'menu'],
];

function fmodia_seed_term_id($taxonomy, $name, array $meta = [])
{
    $term = term_exists($name, $taxonomy);
    if (!$term) {
        $term = wp_insert_term($name, $taxonomy);
    }

    if (is_wp_error($term)) {
        throw new RuntimeException($term->get_error_message());
    }

    $termId = is_array($term) ? (int) $term['term_id'] : (int) $term;
    foreach ($meta as $key => $value) {
        update_term_meta($termId, $key, $value);
    }

    return $termId;
}

function fmodia_seed_existing_post_id($key)
{
    $posts = get_posts([
        'post_type' => 'fm_evento',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_fm_evento_seed_key',
        'meta_value' => $key,
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function fmodia_seed_update_meta($postId, $field, $value)
{
    $key = '_fm_evento_' . $field;
    if ($value === '' || $value === null) {
        delete_post_meta($postId, $key);
        return;
    }

    update_post_meta($postId, $key, (string) $value);
}

foreach ($categories as $name => $color) {
    fmodia_seed_term_id('fm_evento_categoria', $name, ['cor' => $color]);
}

$created = 0;
$updated = 0;
$ids = [];

foreach ($events as $event) {
    $venue = $venues[$event['venue']];
    $sourceUrl = $sources[$event['source']] ?? $sources['menu'];
    $content = sprintf(
        '<p>Registro de teste criado para validar o calendario de eventos FM O Dia.</p><p>Base pesquisada: <a href="%s" target="_blank" rel="noopener">%s</a>.</p><p>As datas deste cadastro foram distribuidas para teste visual e devem ser revisadas antes de uso publico.</p>',
        esc_url($sourceUrl),
        esc_html($sourceUrl)
    );

    $postData = [
        'post_type' => 'fm_evento',
        'post_status' => 'publish',
        'post_title' => $event['title'],
        'post_content' => $content,
        'post_author' => 1,
    ];

    $existingId = fmodia_seed_existing_post_id($event['key']);
    if ($existingId) {
        $postData['ID'] = $existingId;
        $postId = wp_update_post($postData, true);
        $updated++;
    } else {
        $postId = wp_insert_post($postData, true);
        $created++;
    }

    if (is_wp_error($postId)) {
        throw new RuntimeException($postId->get_error_message());
    }

    $categoryId = fmodia_seed_term_id('fm_evento_categoria', $event['cat'], ['cor' => $categories[$event['cat']] ?? '#1976d2']);
    $locationId = fmodia_seed_term_id('fm_evento_local', $venue['name'], [
        'endereco' => $venue['address'],
        'cidade' => $venue['city'],
        'estado' => $venue['state'],
        'cep' => $venue['cep'],
        'lat' => $venue['lat'],
        'lng' => $venue['lng'],
    ]);

    wp_set_object_terms($postId, [$categoryId], 'fm_evento_categoria', false);
    wp_set_object_terms($postId, [$locationId], 'fm_evento_local', false);

    update_post_meta($postId, '_fm_evento_seed_key', $event['key']);
    update_post_meta($postId, '_fm_evento_seed_source', $sourceUrl);
    fmodia_seed_update_meta($postId, 'data_inicio', $event['date']);
    fmodia_seed_update_meta($postId, 'data_fim', $event['date_end'] ?? $event['date']);
    fmodia_seed_update_meta($postId, 'hora_inicio', $event['time'] ?? '');
    fmodia_seed_update_meta($postId, 'hora_fim', $event['end_time'] ?? '');
    fmodia_seed_update_meta($postId, 'local_nome', $venue['name']);
    fmodia_seed_update_meta($postId, 'endereco', $venue['address']);
    fmodia_seed_update_meta($postId, 'cidade', $venue['city']);
    fmodia_seed_update_meta($postId, 'estado', $venue['state']);
    fmodia_seed_update_meta($postId, 'cep', $venue['cep']);
    fmodia_seed_update_meta($postId, 'lat', $venue['lat']);
    fmodia_seed_update_meta($postId, 'lng', $venue['lng']);
    fmodia_seed_update_meta($postId, 'link_ingresso', $event['ticket'] ?? '');
    fmodia_seed_update_meta($postId, 'preco_min', isset($event['prices'][0]) ? (string) $event['prices'][0] : '');
    fmodia_seed_update_meta($postId, 'preco_max', isset($event['prices'][1]) ? (string) $event['prices'][1] : '');
    fmodia_seed_update_meta($postId, 'lineup', implode("\n", $event['lineup'] ?? []));
    fmodia_seed_update_meta($postId, 'classificacao', $event['class'] ?? 'livre');
    fmodia_seed_update_meta($postId, 'status', $event['status'] ?? 'confirmado');

    $ids[] = (int) $postId;
}

printf("Seed completed. Created: %d. Updated: %d. Total: %d.\n", $created, $updated, count($ids));
printf("IDs: %s\n", implode(', ', $ids));
