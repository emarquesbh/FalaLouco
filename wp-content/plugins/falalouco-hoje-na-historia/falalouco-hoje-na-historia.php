<?php
/**
 * Plugin Name: Fala, Louco - Hoje na História
 * Description: Tipo de conteúdo "Fato histórico" (dia, mês, ano, texto, duas fontes, imagem com crédito) e o bloco "Hoje na História", que mostra os fatos da data de hoje. Uso: shortcode [hoje_na_historia].
 * Version: 1.1.0
 * Author: Eduardo Marques
 *
 * Caminho: wp-content/plugins/falalouco-hoje-na-historia/falalouco-hoje-na-historia.php
 * O visual (CSS) fica no tema filho falalouco-child. Compatível com PHP 7.2+.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Importador de planilha (página "Importar planilha" no painel) */
require_once plugin_dir_path(__FILE__) . 'includes/importador.php';

/* Nomes dos meses em português (não depende do idioma instalado) */
function flhh_nomes_meses() {
    return array(
        1  => 'janeiro',
        2  => 'fevereiro',
        3  => 'março',
        4  => 'abril',
        5  => 'maio',
        6  => 'junho',
        7  => 'julho',
        8  => 'agosto',
        9  => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    );
}

/* Escreve a data por extenso: "25 de outubro" ou "25 de outubro de 1966" */
function flhh_data_extenso($dia, $mes, $ano) {
    $meses = flhh_nomes_meses();
    $nome_mes = isset($meses[$mes]) ? $meses[$mes] : '';
    $texto = (int) $dia . ' de ' . $nome_mes;
    if ($ano) {
        $texto .= ' de ' . (int) $ano;
    }
    return $texto;
}

/* ------------------------------------------------------------------
 * 1) Tipo de conteúdo "Fato histórico" e categorias
 * ------------------------------------------------------------------ */
function flhh_registrar_tipos() {
    register_post_type('fato_historico', array(
        'labels' => array(
            'name'               => 'Hoje na História',
            'singular_name'      => 'Fato histórico',
            'add_new'            => 'Adicionar fato',
            'add_new_item'       => 'Adicionar novo fato histórico',
            'edit_item'          => 'Editar fato histórico',
            'new_item'           => 'Novo fato histórico',
            'view_item'          => 'Ver fato histórico',
            'search_items'       => 'Buscar fatos',
            'not_found'          => 'Nenhum fato encontrado',
            'not_found_in_trash' => 'Nenhum fato na lixeira',
            'all_items'          => 'Todos os fatos',
            'menu_name'          => 'Hoje na História',
        ),
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => array('slug' => 'hoje-na-historia'),
        'menu_icon'     => 'dashicons-calendar-alt',
        'menu_position' => 6,
        'supports'      => array('title', 'editor', 'thumbnail'),
        'show_in_rest'  => true,
    ));

    register_taxonomy('categoria_fato', 'fato_historico', array(
        'labels' => array(
            'name'          => 'Categorias de fatos',
            'singular_name' => 'Categoria de fato',
            'add_new_item'  => 'Adicionar categoria',
            'menu_name'     => 'Categorias',
        ),
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'tipo-de-fato'),
    ));
}
add_action('init', 'flhh_registrar_tipos');

/* ------------------------------------------------------------------
 * 2) Ativação e desativação
 * ------------------------------------------------------------------ */
function flhh_ativar() {
    flhh_registrar_tipos();

    $categorias = array(
        'Títulos',
        'Ídolos',
        'Jogos históricos',
        'Clube e bastidores',
        'Torcida e cultura',
        'Base e feminino',
    );
    foreach ($categorias as $nome) {
        if (!term_exists($nome, 'categoria_fato')) {
            wp_insert_term($nome, 'categoria_fato');
        }
    }

    flush_rewrite_rules();

    /* Coloca o bloco na página Início, só se ela ainda tiver o texto provisório original */
    $inicio = get_page_by_path('inicio');
    if ($inicio) {
        $original = '<p>Em construção. Aqui vai a página inicial: últimas notícias, Hoje na História e destaques da Opinião.</p>';
        if (trim($inicio->post_content) === $original) {
            wp_update_post(array(
                'ID'           => $inicio->ID,
                'post_content' => '[hoje_na_historia]' . "\n\n" . $original,
            ));
        }
    }
}
register_activation_hook(__FILE__, 'flhh_ativar');

function flhh_desativar() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'flhh_desativar');

/* ------------------------------------------------------------------
 * 3) Caixa de campos no editor do fato
 * ------------------------------------------------------------------ */
function flhh_metabox_registrar() {
    add_meta_box(
        'flhh_dados',
        'Dados do fato histórico',
        'flhh_metabox_html',
        'fato_historico',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'flhh_metabox_registrar');

function flhh_linha($rotulo, $campo) {
    echo '<tr><th scope="row" style="width:180px;">' . esc_html($rotulo) . '</th><td>' . $campo . '</td></tr>';
}

function flhh_metabox_html($post) {
    wp_nonce_field('flhh_salvar', 'flhh_nonce');

    $dia   = get_post_meta($post->ID, '_flhh_dia', true);
    $mes   = (int) get_post_meta($post->ID, '_flhh_mes', true);
    $ano   = get_post_meta($post->ID, '_flhh_ano', true);
    $f1n   = get_post_meta($post->ID, '_flhh_fonte1_nome', true);
    $f1u   = get_post_meta($post->ID, '_flhh_fonte1_url', true);
    $f2n   = get_post_meta($post->ID, '_flhh_fonte2_nome', true);
    $f2u   = get_post_meta($post->ID, '_flhh_fonte2_url', true);
    $cred  = get_post_meta($post->ID, '_flhh_credito_imagem', true);
    $meses = flhh_nomes_meses();

    echo '<p><em>Preencha a data e as duas fontes. Escreva o texto do fato (no editor acima) com palavras próprias. ';
    echo 'Sem data válida e sem as duas fontes, o fato fica como rascunho e não é publicado.</em></p>';
    echo '<table class="form-table"><tbody>';

    flhh_linha('Dia (1 a 31)', '<input type="number" name="flhh_dia" min="1" max="31" value="' . esc_attr($dia) . '" style="width:80px;">');

    $opcoes = '<option value="">Escolha o mês</option>';
    foreach ($meses as $numero => $nome) {
        $opcoes .= '<option value="' . (int) $numero . '"' . selected($mes, $numero, false) . '>' . esc_html(ucfirst($nome)) . '</option>';
    }
    flhh_linha('Mês', '<select name="flhh_mes">' . $opcoes . '</select>');

    flhh_linha('Ano', '<input type="number" name="flhh_ano" min="1800" max="2100" value="' . esc_attr($ano) . '" style="width:100px;">');

    flhh_linha('Fonte 1 (nome)', '<input type="text" name="flhh_fonte1_nome" value="' . esc_attr($f1n) . '" class="regular-text">');
    flhh_linha('Fonte 1 (link)', '<input type="url" name="flhh_fonte1_url" value="' . esc_attr($f1u) . '" class="large-text" placeholder="https://">');
    flhh_linha('Fonte 2 (nome)', '<input type="text" name="flhh_fonte2_nome" value="' . esc_attr($f2n) . '" class="regular-text">');
    flhh_linha('Fonte 2 (link)', '<input type="url" name="flhh_fonte2_url" value="' . esc_attr($f2u) . '" class="large-text" placeholder="https://">');

    flhh_linha('Crédito da imagem', '<input type="text" name="flhh_credito_imagem" value="' . esc_attr($cred) . '" class="large-text" placeholder="Ex.: Autor / Wikimedia Commons (licença)"><p class="description">A imagem do fato é a "Imagem destacada". Só use imagem própria, de acervo livre ou autorizada.</p>');

    echo '</tbody></table>';
}

/* Confere se a data existe de verdade (ex.: 31 de abril não vale) */
function flhh_data_valida($dia, $mes, $ano) {
    if ($ano < 1800 || $ano > 2100) {
        return false;
    }
    return checkdate((int) $mes, (int) $dia, (int) $ano);
}

/* Salva os campos e aplica a regra de publicação */
function flhh_salvar($post_id, $post) {
    if (!isset($_POST['flhh_nonce']) || !wp_verify_nonce($_POST['flhh_nonce'], 'flhh_salvar')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $dia  = isset($_POST['flhh_dia']) ? absint($_POST['flhh_dia']) : 0;
    $mes  = isset($_POST['flhh_mes']) ? absint($_POST['flhh_mes']) : 0;
    $ano  = isset($_POST['flhh_ano']) ? absint($_POST['flhh_ano']) : 0;
    $f1n  = isset($_POST['flhh_fonte1_nome']) ? sanitize_text_field(wp_unslash($_POST['flhh_fonte1_nome'])) : '';
    $f1u  = isset($_POST['flhh_fonte1_url']) ? esc_url_raw(wp_unslash($_POST['flhh_fonte1_url']), array('http', 'https')) : '';
    $f2n  = isset($_POST['flhh_fonte2_nome']) ? sanitize_text_field(wp_unslash($_POST['flhh_fonte2_nome'])) : '';
    $f2u  = isset($_POST['flhh_fonte2_url']) ? esc_url_raw(wp_unslash($_POST['flhh_fonte2_url']), array('http', 'https')) : '';
    $cred = isset($_POST['flhh_credito_imagem']) ? sanitize_text_field(wp_unslash($_POST['flhh_credito_imagem'])) : '';

    update_post_meta($post_id, '_flhh_dia', $dia);
    update_post_meta($post_id, '_flhh_mes', $mes);
    update_post_meta($post_id, '_flhh_ano', $ano);
    update_post_meta($post_id, '_flhh_fonte1_nome', $f1n);
    update_post_meta($post_id, '_flhh_fonte1_url', $f1u);
    update_post_meta($post_id, '_flhh_fonte2_nome', $f2n);
    update_post_meta($post_id, '_flhh_fonte2_url', $f2u);
    update_post_meta($post_id, '_flhh_credito_imagem', $cred);

    /* Regra: só publica com data válida e as duas fontes completas */
    if ($post->post_status !== 'publish') {
        return;
    }
    $problemas = array();
    if (!flhh_data_valida($dia, $mes, $ano)) {
        $problemas[] = 'a data (dia, mês e ano) está vazia ou não existe';
    }
    if ($f1n === '' || $f1u === '' || $f2n === '' || $f2u === '') {
        $problemas[] = 'faltam as duas fontes (nome e link de cada uma)';
    }
    if (!empty($problemas)) {
        remove_action('save_post_fato_historico', 'flhh_salvar', 10);
        wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
        add_action('save_post_fato_historico', 'flhh_salvar', 10, 2);
        set_transient(
            'flhh_aviso_' . get_current_user_id(),
            'O fato foi salvo como rascunho porque ' . implode(' e ', $problemas) . '.',
            120
        );
    }
}
add_action('save_post_fato_historico', 'flhh_salvar', 10, 2);

/* Aviso no painel quando o fato volta para rascunho */
function flhh_aviso_admin() {
    $chave = 'flhh_aviso_' . get_current_user_id();
    $msg = get_transient($chave);
    if (!$msg) {
        return;
    }
    delete_transient($chave);
    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($msg) . '</p></div>';
}
add_action('admin_notices', 'flhh_aviso_admin');

/* ------------------------------------------------------------------
 * 4) Colunas na lista de fatos (painel)
 * ------------------------------------------------------------------ */
function flhh_colunas($colunas) {
    $novas = array();
    foreach ($colunas as $chave => $titulo) {
        $novas[$chave] = $titulo;
        if ($chave === 'title') {
            $novas['flhh_data'] = 'Data do fato';
            $novas['flhh_fontes'] = 'Fontes';
        }
    }
    return $novas;
}
add_filter('manage_fato_historico_posts_columns', 'flhh_colunas');

function flhh_coluna_conteudo($coluna, $post_id) {
    if ($coluna === 'flhh_data') {
        $dia = (int) get_post_meta($post_id, '_flhh_dia', true);
        $mes = (int) get_post_meta($post_id, '_flhh_mes', true);
        $ano = (int) get_post_meta($post_id, '_flhh_ano', true);
        if ($dia && $mes) {
            echo esc_html(sprintf('%02d/%02d/%d', $dia, $mes, $ano));
        } else {
            echo '—';
        }
    }
    if ($coluna === 'flhh_fontes') {
        $f1 = get_post_meta($post_id, '_flhh_fonte1_url', true);
        $f2 = get_post_meta($post_id, '_flhh_fonte2_url', true);
        echo ($f1 && $f2) ? 'Completas' : 'Faltando';
    }
}
add_action('manage_fato_historico_posts_custom_column', 'flhh_coluna_conteudo', 10, 2);

/* ------------------------------------------------------------------
 * 5) Busca dos fatos de uma data
 * ------------------------------------------------------------------ */
function flhh_fatos_da_data($dia, $mes, $limite) {
    return get_posts(array(
        'post_type'      => 'fato_historico',
        'post_status'    => 'publish',
        'posts_per_page' => $limite,
        'meta_key'       => '_flhh_ano',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_flhh_dia',
                'value'   => (int) $dia,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => '_flhh_mes',
                'value'   => (int) $mes,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ));
}

/* Quantos dias faltam até a próxima ocorrência de uma data (9999 = não achou) */
function flhh_dias_ate($dia, $mes, $hoje_dia, $hoje_mes, $hoje_ano) {
    $hoje = gmmktime(0, 0, 0, $hoje_mes, $hoje_dia, $hoje_ano);
    for ($ano = $hoje_ano; $ano <= $hoje_ano + 4; $ano++) {
        if (!checkdate($mes, $dia, $ano)) {
            continue;
        }
        $alvo = gmmktime(0, 0, 0, $mes, $dia, $ano);
        if ($alvo >= $hoje) {
            return (int) (($alvo - $hoje) / 86400);
        }
    }
    return 9999;
}

/* Fatos de hoje; se não houver, os da data cadastrada mais próxima */
function flhh_buscar($limite) {
    $hoje_dia = (int) date_i18n('j');
    $hoje_mes = (int) date_i18n('n');
    $hoje_ano = (int) date_i18n('Y');

    $fatos = flhh_fatos_da_data($hoje_dia, $hoje_mes, $limite);
    if (!empty($fatos)) {
        return array('fatos' => $fatos, 'hoje' => true, 'dia' => $hoje_dia, 'mes' => $hoje_mes);
    }

    $ids = get_posts(array(
        'post_type'      => 'fato_historico',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ));
    $melhor = 99999;
    $melhor_dia = 0;
    $melhor_mes = 0;
    foreach ($ids as $id) {
        $d = (int) get_post_meta($id, '_flhh_dia', true);
        $m = (int) get_post_meta($id, '_flhh_mes', true);
        $dist = flhh_dias_ate($d, $m, $hoje_dia, $hoje_mes, $hoje_ano);
        if ($dist < $melhor) {
            $melhor = $dist;
            $melhor_dia = $d;
            $melhor_mes = $m;
        }
    }
    if ($melhor >= 9999) {
        return array('fatos' => array(), 'hoje' => false, 'dia' => 0, 'mes' => 0);
    }
    return array(
        'fatos' => flhh_fatos_da_data($melhor_dia, $melhor_mes, $limite),
        'hoje'  => false,
        'dia'   => $melhor_dia,
        'mes'   => $melhor_mes,
    );
}

/* ------------------------------------------------------------------
 * 6) Bloco "Hoje na História": shortcode [hoje_na_historia limite="3"]
 * ------------------------------------------------------------------ */
function flhh_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limite' => 3,
        'titulo' => 'Hoje na História',
    ), $atts, 'hoje_na_historia');

    $limite = max(1, min(12, absint($atts['limite'])));
    $dados = flhh_buscar($limite);
    if (empty($dados['fatos'])) {
        return '';
    }

    $html  = '<section class="flhh-faixa">';
    $html .= '<header class="flhh-cabecalho">';
    $html .= '<h2 class="flhh-titulo">' . esc_html($atts['titulo']) . '</h2>';
    $html .= '<p class="flhh-data">' . esc_html(flhh_data_extenso($dados['dia'], $dados['mes'], 0)) . '</p>';
    $html .= '</header>';

    if (!$dados['hoje']) {
        $html .= '<p class="flhh-aviso">Nenhum fato cadastrado para hoje. O próximo é de ' . esc_html(flhh_data_extenso($dados['dia'], $dados['mes'], 0)) . '.</p>';
    }

    $html .= '<div class="flhh-lista">';
    foreach ($dados['fatos'] as $fato) {
        $ano = (int) get_post_meta($fato->ID, '_flhh_ano', true);
        $cred = get_post_meta($fato->ID, '_flhh_credito_imagem', true);
        $link = get_permalink($fato->ID);
        $resumo = wp_trim_words(wp_strip_all_tags(strip_shortcodes($fato->post_content)), 40, '…');
        $cats = get_the_terms($fato->ID, 'categoria_fato');

        $html .= '<article class="flhh-card">';
        if (has_post_thumbnail($fato->ID)) {
            $html .= '<a class="flhh-imagem" href="' . esc_url($link) . '">' . get_the_post_thumbnail($fato->ID, 'medium_large') . '</a>';
        }
        $html .= '<div class="flhh-corpo">';
        $html .= '<p class="flhh-selo"><span class="flhh-ano">' . esc_html($ano) . '</span>';
        if (!empty($cats) && !is_wp_error($cats)) {
            $html .= ' <span class="flhh-categoria">' . esc_html($cats[0]->name) . '</span>';
        }
        $html .= '</p>';
        $html .= '<h3 class="flhh-card-titulo"><a href="' . esc_url($link) . '">' . esc_html(get_the_title($fato->ID)) . '</a></h3>';
        $html .= '<p class="flhh-resumo">' . esc_html($resumo) . '</p>';
        if (has_post_thumbnail($fato->ID) && $cred) {
            $html .= '<p class="flhh-credito">Imagem: ' . esc_html($cred) . '</p>';
        }
        $html .= '</div>';
        $html .= '</article>';
    }
    $html .= '</div>';

    $html .= '<p class="flhh-ver-todos"><a href="' . esc_url(get_post_type_archive_link('fato_historico')) . '">Ver todos os fatos</a></p>';
    $html .= '</section>';

    return $html;
}
add_shortcode('hoje_na_historia', 'flhh_shortcode');

/* ------------------------------------------------------------------
 * 7) Página de cada fato: data no começo, fontes no fim
 * ------------------------------------------------------------------ */
function flhh_conteudo_single($conteudo) {
    if (!is_singular('fato_historico') || !in_the_loop() || !is_main_query()) {
        return $conteudo;
    }
    $id = get_the_ID();
    $dia = (int) get_post_meta($id, '_flhh_dia', true);
    $mes = (int) get_post_meta($id, '_flhh_mes', true);
    $ano = (int) get_post_meta($id, '_flhh_ano', true);
    $f1n = get_post_meta($id, '_flhh_fonte1_nome', true);
    $f1u = get_post_meta($id, '_flhh_fonte1_url', true);
    $f2n = get_post_meta($id, '_flhh_fonte2_nome', true);
    $f2u = get_post_meta($id, '_flhh_fonte2_url', true);
    $cred = get_post_meta($id, '_flhh_credito_imagem', true);

    $antes = '<p class="flhh-data-fato">' . esc_html(flhh_data_extenso($dia, $mes, $ano)) . '</p>';

    $depois = '<div class="flhh-fontes">';
    if ($cred && has_post_thumbnail($id)) {
        $depois .= '<p class="flhh-credito">Imagem: ' . esc_html($cred) . '</p>';
    }
    $depois .= '<p><strong>Fontes:</strong> ';
    $depois .= '<a href="' . esc_url($f1u) . '" target="_blank" rel="noopener nofollow">' . esc_html($f1n) . '</a>';
    $depois .= ' · ';
    $depois .= '<a href="' . esc_url($f2u) . '" target="_blank" rel="noopener nofollow">' . esc_html($f2n) . '</a>';
    $depois .= '</p></div>';

    return $antes . $conteudo . $depois;
}
add_filter('the_content', 'flhh_conteudo_single');

/* ------------------------------------------------------------------
 * 8) Página com todos os fatos: ordem do calendário (janeiro a dezembro)
 * ------------------------------------------------------------------ */
function flhh_ordem_arquivo($consulta) {
    if (is_admin() || !$consulta->is_main_query()) {
        return;
    }
    if (!$consulta->is_post_type_archive('fato_historico') && !$consulta->is_tax('categoria_fato')) {
        return;
    }
    $consulta->set('meta_query', array(
        'relation' => 'AND',
        'flhh_mes' => array('key' => '_flhh_mes', 'compare' => 'EXISTS', 'type' => 'NUMERIC'),
        'flhh_dia' => array('key' => '_flhh_dia', 'compare' => 'EXISTS', 'type' => 'NUMERIC'),
        'flhh_ano' => array('key' => '_flhh_ano', 'compare' => 'EXISTS', 'type' => 'NUMERIC'),
    ));
    $consulta->set('orderby', array('flhh_mes' => 'ASC', 'flhh_dia' => 'ASC', 'flhh_ano' => 'ASC'));
    $consulta->set('posts_per_page', 24);
}
add_action('pre_get_posts', 'flhh_ordem_arquivo');
