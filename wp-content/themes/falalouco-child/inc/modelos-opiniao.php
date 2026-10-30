<?php
/**
 * Arquivo: modelos-opiniao.php do tema filho Fala, Louco.
 * Caminho: wp-content/themes/falalouco-child/inc/modelos-opiniao.php
 * O que faz: cria no editor do WordPress três modelos de texto prontos
 * (Pós-jogo, Bate-pronto e Resenha), na aba "Padrões", categoria
 * "Fala, Louco". Ao criar um post, é só inserir o modelo e preencher.
 * Compatível com PHP 7.2+ (WordPress 6.1 ou mais novo).
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Blocos de texto usados nos modelos */
function fl_modelo_titulo($texto) {
    return '<!-- wp:heading -->' . "\n"
        . '<h2 class="wp-block-heading">' . esc_html($texto) . '</h2>' . "\n"
        . '<!-- /wp:heading -->' . "\n\n";
}

function fl_modelo_paragrafo($texto) {
    return '<!-- wp:paragraph -->' . "\n"
        . '<p>' . esc_html($texto) . '</p>' . "\n"
        . '<!-- /wp:paragraph -->' . "\n\n";
}

function fl_modelo_lista($itens) {
    $html = '<!-- wp:list -->' . "\n" . '<ul class="wp-block-list">';
    foreach ($itens as $item) {
        $html .= '<!-- wp:list-item -->' . "\n"
            . '<li>' . esc_html($item) . '</li>' . "\n"
            . '<!-- /wp:list-item -->';
    }
    $html .= '</ul>' . "\n" . '<!-- /wp:list -->' . "\n\n";
    return $html;
}

/* Modelo: Pós-jogo (texto de 300 a 600 palavras) */
function fl_modelo_pos_jogo() {
    $html  = fl_modelo_titulo('O resultado');
    $html .= fl_modelo_paragrafo('Escreva aqui: placar, adversário, competição, data e local, em uma ou duas frases, com suas palavras.');
    $html .= fl_modelo_titulo('O que eu senti');
    $html .= fl_modelo_paragrafo('Escreva aqui: como foi viver esse jogo como torcedor. Emoção, tensão, alegria ou raiva, sem análise tática.');
    $html .= fl_modelo_titulo('Quem se destacou');
    $html .= fl_modelo_lista(array(
        'Nome do jogador: em uma frase, por que chamou a atenção.',
        'Nome do jogador: em uma frase, por que chamou a atenção.',
    ));
    $html .= fl_modelo_titulo('O lance que ficou');
    $html .= fl_modelo_paragrafo('Escreva aqui: o momento do jogo que você não vai esquecer e por quê.');
    $html .= fl_modelo_titulo('E agora?');
    $html .= fl_modelo_paragrafo('Escreva aqui: próximo jogo, o que a torcida espera e um convite para comentar.');
    $html .= fl_modelo_paragrafo('Dados do jogo (placar, gols, horários): fonte e link.');
    return $html;
}

/* Modelo: Bate-pronto (texto curto, de 150 a 300 palavras) */
function fl_modelo_bate_pronto() {
    $html  = fl_modelo_titulo('O assunto');
    $html .= fl_modelo_paragrafo('Escreva aqui: do que se trata, em uma frase.');
    $html .= fl_modelo_titulo('Minha opinião');
    $html .= fl_modelo_paragrafo('Escreva aqui: sua opinião, direto ao ponto, em um ou dois parágrafos curtos.');
    $html .= fl_modelo_titulo('Por quê');
    $html .= fl_modelo_lista(array(
        'Primeiro motivo.',
        'Segundo motivo.',
        'Terceiro motivo.',
    ));
    $html .= fl_modelo_titulo('E você, o que acha?');
    $html .= fl_modelo_paragrafo('Escreva aqui: uma pergunta para a torcida responder nos comentários.');
    $html .= fl_modelo_paragrafo('Fonte da informação citada: nome e link.');
    return $html;
}

/* Modelo: Resenha (texto mais longo, de 600 a 1.200 palavras) */
function fl_modelo_resenha() {
    $html  = fl_modelo_paragrafo('Escreva aqui: abertura que prende. Uma cena, uma lembrança ou uma pergunta.');
    $html .= fl_modelo_titulo('A conversa de hoje');
    $html .= fl_modelo_paragrafo('Escreva aqui: qual é o tema e por que ele importa para o torcedor.');
    $html .= fl_modelo_titulo('O que se diz por aí');
    $html .= fl_modelo_paragrafo('Escreva aqui: o que a imprensa e a torcida estão comentando. Marque o que é confirmado e o que é especulação, sempre com a fonte.');
    $html .= fl_modelo_titulo('O que eu penso');
    $html .= fl_modelo_paragrafo('Escreva aqui: sua posição, com calma e personalidade, sem ofender pessoas.');
    $html .= fl_modelo_titulo('Uma lembrança do clube');
    $html .= fl_modelo_paragrafo('Escreva aqui: um episódio da história do Cruzeiro que se liga ao tema.');
    $html .= fl_modelo_titulo('Fechando a resenha');
    $html .= fl_modelo_paragrafo('Escreva aqui: conclusão em poucas linhas e um convite para a torcida participar.');
    $html .= fl_modelo_titulo('Fontes');
    $html .= fl_modelo_lista(array(
        'Nome da fonte: link.',
        'Nome da fonte: link.',
    ));
    return $html;
}

/* Registra os modelos no editor */
function fl_modelos_opiniao_registrar() {
    if (!function_exists('register_block_pattern') || !function_exists('register_block_pattern_category')) {
        return;
    }

    register_block_pattern_category('fala-louco', array('label' => 'Fala, Louco'));

    register_block_pattern('falalouco/modelo-pos-jogo', array(
        'title'       => 'Modelo: Pós-jogo',
        'description' => 'Roteiro de pós-jogo: resultado, sentimento, destaques, lance marcante e próximo passo.',
        'categories'  => array('fala-louco'),
        'postTypes'   => array('post'),
        'content'     => fl_modelo_pos_jogo(),
    ));
    register_block_pattern('falalouco/modelo-bate-pronto', array(
        'title'       => 'Modelo: Bate-pronto',
        'description' => 'Roteiro de opinião curta: assunto, opinião, três motivos e pergunta para a torcida.',
        'categories'  => array('fala-louco'),
        'postTypes'   => array('post'),
        'content'     => fl_modelo_bate_pronto(),
    ));
    register_block_pattern('falalouco/modelo-resenha', array(
        'title'       => 'Modelo: Resenha',
        'description' => 'Roteiro de resenha longa: abertura, tema, o que se diz, opinião, lembrança do clube e fontes.',
        'categories'  => array('fala-louco'),
        'postTypes'   => array('post'),
        'content'     => fl_modelo_resenha(),
    ));
}
add_action('init', 'fl_modelos_opiniao_registrar');
