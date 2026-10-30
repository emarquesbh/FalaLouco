<?php
/**
 * Arquivo: home.php do tema filho Fala, Louco.
 * Caminho: wp-content/themes/falalouco-child/inc/home.php
 * O que faz: funções que montam os blocos da página inicial (boas-vindas,
 * Hoje na História, Últimas notícias e Destaques da Opinião) e deixam a
 * página inicial sem barra lateral. Usado pelo front-page.php.
 * Compatível com PHP 7.2+.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Página inicial sem barra lateral (filtro do Astra) */
function fl_home_layout($layout) {
    if (is_front_page()) {
        return 'no-sidebar';
    }
    return $layout;
}
add_filter('astra_page_layout', 'fl_home_layout');

/* Endereço de uma categoria pelo slug (se não existir, volta para o início) */
function fl_home_link_categoria($slug) {
    $categoria = get_category_by_slug($slug);
    if ($categoria) {
        return get_category_link($categoria->term_id);
    }
    return home_url('/');
}

/* Resumo curto de um texto: usa o resumo manual, ou as primeiras palavras */
function fl_home_resumo($post, $palavras) {
    if (has_excerpt($post->ID)) {
        $texto = $post->post_excerpt;
    } else {
        $texto = $post->post_content;
    }
    $texto = wp_strip_all_tags(strip_shortcodes($texto));
    return wp_trim_words($texto, $palavras, '…');
}

/* Selo de uma notícia: nome da subcategoria (Últimas, Mercado da bola...) */
function fl_home_selo_noticia($post_id) {
    $nome = 'Notícia';
    $pai = get_category_by_slug('noticias');
    if ($pai) {
        $categorias = get_the_category($post_id);
        if (!empty($categorias)) {
            foreach ($categorias as $cat) {
                if ((int) $cat->parent === (int) $pai->term_id) {
                    $nome = $cat->name;
                    break;
                }
            }
        }
    }
    return '<span class="fl-selo fl-selo--noticia">' . esc_html($nome) . '</span>';
}

/* Selo de um texto de opinião: Pós-jogo, Bate-pronto ou Resenha */
function fl_home_selo_opiniao($post_id) {
    $tipo = fl_opiniao_do_post($post_id);
    if (empty($tipo)) {
        $tipo = array('slug' => 'opiniao', 'nome' => 'Opinião');
    }
    return '<span class="fl-selo fl-selo--' . esc_attr($tipo['slug']) . '">' . esc_html($tipo['nome']) . '</span>';
}

/* Cartão de um texto */
function fl_home_card($post, $selo) {
    $link = get_permalink($post->ID);
    $html  = '<article class="fl-card">';
    if (has_post_thumbnail($post->ID)) {
        $html .= '<a class="fl-card-img" href="' . esc_url($link) . '">' . get_the_post_thumbnail($post->ID, 'medium_large') . '</a>';
    }
    $html .= '<div class="fl-card-corpo">';
    $html .= $selo;
    $html .= '<h3 class="fl-card-titulo"><a href="' . esc_url($link) . '">' . esc_html(get_the_title($post->ID)) . '</a></h3>';
    $html .= '<p class="fl-card-data">' . esc_html(get_the_date('', $post)) . '</p>';
    $html .= '<p class="fl-card-resumo">' . esc_html(fl_home_resumo($post, 28)) . '</p>';
    $html .= '</div></article>';
    return $html;
}

/* Moldura de uma seção: título, link "ver todos" e a grade de cartões (ou aviso se vazia) */
function fl_home_secao($classe, $titulo, $link, $rotulo_link, $cartoes, $vazio) {
    $html  = '<section class="fl-home-secao ' . esc_attr($classe) . '">';
    $html .= '<header class="fl-secao-cab">';
    $html .= '<h2>' . esc_html($titulo) . '</h2>';
    $html .= '<a class="fl-secao-link" href="' . esc_url($link) . '">' . esc_html($rotulo_link) . '</a>';
    $html .= '</header>';
    if ($cartoes === '') {
        $html .= '<p class="fl-home-vazio">' . esc_html($vazio) . '</p>';
    } else {
        $html .= '<div class="fl-grade">' . $cartoes . '</div>';
    }
    $html .= '</section>';
    return $html;
}

/* Bloco 1: boas-vindas */
function fl_home_boas_vindas() {
    $html  = '<section class="fl-hero">';
    $html .= '<p class="fl-hero-selo">Site de torcedor · não oficial</p>';
    $html .= '<h1 class="fl-hero-titulo">' . esc_html(get_bloginfo('name')) . '</h1>';
    $html .= '<p class="fl-hero-sub">' . esc_html(get_bloginfo('description')) . '</p>';
    $html .= '<p class="fl-hero-botoes">';
    $html .= '<a class="fl-botao fl-botao--ouro" href="' . esc_url(fl_home_link_categoria('opiniao')) . '">Ler a Opinião</a>';
    $html .= '<a class="fl-botao fl-botao--borda" href="' . esc_url(fl_home_link_categoria('noticias')) . '">Ver as notícias</a>';
    $html .= '</p>';
    $html .= '</section>';
    echo $html;
}

/* Bloco 2: Hoje na História (vem do plugin; some se não houver fatos) */
function fl_home_hoje() {
    if (shortcode_exists('hoje_na_historia')) {
        echo do_shortcode('[hoje_na_historia limite="3"]');
    }
}

/* Bloco 3: últimas notícias */
function fl_home_noticias() {
    $textos = get_posts(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 6,
        'category_name'       => 'noticias',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));
    $cartoes = '';
    foreach ($textos as $texto) {
        $cartoes .= fl_home_card($texto, fl_home_selo_noticia($texto->ID));
    }
    echo fl_home_secao(
        'fl-home-noticias',
        'Últimas notícias',
        fl_home_link_categoria('noticias'),
        'Ver todas as notícias',
        $cartoes,
        'Em breve: as primeiras notícias do Cruzeiro, resumidas com crédito e link da fonte.'
    );
}

/* Bloco 4: destaques da Opinião */
function fl_home_opiniao() {
    $textos = get_posts(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 3,
        'category_name'       => 'opiniao',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));
    $cartoes = '';
    foreach ($textos as $texto) {
        $cartoes .= fl_home_card($texto, fl_home_selo_opiniao($texto->ID));
    }
    echo fl_home_secao(
        'fl-home-opiniao',
        'Destaques da Opinião',
        fl_home_link_categoria('opiniao'),
        'Ver toda a Opinião',
        $cartoes,
        'Em breve: pós-jogos, bate-pronto e resenhas direto da arquibancada.'
    );
}
