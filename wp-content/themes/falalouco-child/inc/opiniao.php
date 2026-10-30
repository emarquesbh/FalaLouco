<?php
/**
 * Arquivo: opiniao.php do tema filho Fala, Louco.
 * Caminho: wp-content/themes/falalouco-child/inc/opiniao.php
 * O que faz: visual da seção Opinião. Nas páginas da seção mostra os três
 * tipos de texto (Pós-jogo, Bate-pronto, Resenha). Em cada texto de opinião
 * coloca o selo do tipo no topo e, no fim, o aviso de opinião pessoal e
 * mais textos do mesmo tipo. Compatível com PHP 7.2+.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Os três tipos de texto da seção (o slug é o da categoria) */
function fl_opiniao_tipos() {
    return array(
        'pos-jogo' => array(
            'nome'      => 'Pós-jogo',
            'descricao' => 'A conversa depois do apito final: o que a torcida sentiu, quem se destacou e o que vem pela frente.',
        ),
        'bate-pronto' => array(
            'nome'      => 'Bate-pronto',
            'descricao' => 'Opinião rápida, direto ao ponto, sobre o assunto do momento.',
        ),
        'resenha' => array(
            'nome'      => 'Resenha',
            'descricao' => 'Papo de torcedor: textos mais longos, com calma e personalidade.',
        ),
    );
}

/* Diz se um texto é de Opinião e de qual tipo. Devolve array(slug, nome) ou array() */
function fl_opiniao_do_post($post_id) {
    $tipos = fl_opiniao_tipos();
    $pai = get_category_by_slug('opiniao');
    if (!$pai) {
        return array();
    }
    $achou = false;
    $tipo = array();
    $categorias = get_the_category($post_id);
    if (empty($categorias)) {
        return array();
    }
    foreach ($categorias as $cat) {
        if ((int) $cat->term_id === (int) $pai->term_id) {
            $achou = true;
        }
        if ((int) $cat->parent === (int) $pai->term_id) {
            $achou = true;
            if (empty($tipo) && isset($tipos[$cat->slug])) {
                $tipo = array('slug' => $cat->slug, 'nome' => $cat->name);
            }
        }
    }
    if (!$achou) {
        return array();
    }
    if (empty($tipo)) {
        $tipo = array('slug' => 'opiniao', 'nome' => 'Opinião');
    }
    return $tipo;
}

/* Lista curta "Mais de ..." com outros textos do mesmo tipo */
function fl_opiniao_relacionados($post_id, $tipo) {
    $outros = get_posts(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 3,
        'post__not_in'        => array($post_id),
        'category_name'       => $tipo['slug'],
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));
    if (empty($outros)) {
        return '';
    }
    $html = '<section class="fl-opiniao-mais"><h3>Mais de ' . esc_html($tipo['nome']) . '</h3><ul>';
    foreach ($outros as $outro) {
        $html .= '<li><a href="' . esc_url(get_permalink($outro->ID)) . '">' . esc_html(get_the_title($outro->ID)) . '</a>';
        $html .= '<span>' . esc_html(get_the_date('', $outro->ID)) . '</span></li>';
    }
    $html .= '</ul></section>';
    return $html;
}

/* Em cada texto de Opinião: selo e tempo de leitura no topo, aviso e relacionados no fim */
function fl_opiniao_conteudo($conteudo) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $conteudo;
    }
    $id = get_the_ID();
    $tipo = fl_opiniao_do_post($id);
    if (empty($tipo)) {
        return $conteudo;
    }

    $palavras = preg_match_all('/\S+/u', wp_strip_all_tags($conteudo), $achados);
    $minutos = max(1, (int) ceil($palavras / 200));

    $topo  = '<p class="fl-opiniao-topo">';
    $topo .= '<span class="fl-selo fl-selo--' . esc_attr($tipo['slug']) . '">' . esc_html($tipo['nome']) . '</span>';
    $topo .= '<span class="fl-opiniao-meta">' . esc_html(get_the_date()) . ' · ' . (int) $minutos . ' min de leitura</span>';
    $topo .= '</p>';

    $rodape  = '<aside class="fl-opiniao-aviso"><strong>Opinião de torcedor.</strong> ';
    $rodape .= 'Este texto é a opinião pessoal do autor. O Fala, Louco é um site não oficial, sem vínculo com o Cruzeiro Esporte Clube.</aside>';
    $rodape .= fl_opiniao_relacionados($id, $tipo);

    return $topo . $conteudo . $rodape;
}
add_filter('the_content', 'fl_opiniao_conteudo');

/* Nas páginas da seção Opinião e dos três tipos: cartões de apresentação no topo da lista */
function fl_opiniao_apresentacao($consulta) {
    static $feito = false;
    if ($feito || !$consulta->is_main_query() || !is_category()) {
        return;
    }
    $atual = get_queried_object();
    $pai = get_category_by_slug('opiniao');
    if (!$pai || !$atual || !isset($atual->term_id)) {
        return;
    }
    if ((int) $atual->term_id !== (int) $pai->term_id && (int) $atual->parent !== (int) $pai->term_id) {
        return;
    }
    $feito = true;

    $html = '<nav class="fl-opiniao-tipos" aria-label="Tipos de texto da Opinião">';
    foreach (fl_opiniao_tipos() as $slug => $dados) {
        $categoria = get_category_by_slug($slug);
        if (!$categoria) {
            continue;
        }
        $classe = 'fl-tipo fl-tipo--' . $slug;
        if ((int) $categoria->term_id === (int) $atual->term_id) {
            $classe .= ' is-atual';
        }
        $html .= '<a class="' . esc_attr($classe) . '" href="' . esc_url(get_category_link($categoria->term_id)) . '">';
        $html .= '<strong>' . esc_html($dados['nome']) . '</strong>';
        $html .= '<span>' . esc_html($dados['descricao']) . '</span>';
        $html .= '</a>';
    }
    $html .= '</nav>';
    echo $html;
}
add_action('loop_start', 'fl_opiniao_apresentacao', 10, 1);
