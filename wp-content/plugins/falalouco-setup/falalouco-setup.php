<?php
/**
 * Plugin Name: Fala, Louco - Configuração Inicial
 * Description: Roda uma vez ao ativar: ajusta o WordPress, cria categorias, páginas base e o menu principal. Depois de usar, pode desativar e excluir.
 * Version: 1.0.0
 * Author: Eduardo Marques
 *
 * Caminho: wp-content/plugins/falalouco-setup/falalouco-setup.php
 * Compatível com PHP 7.2+. Não apaga nada: só cria o que ainda não existe.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Cria uma categoria se ela ainda não existir e devolve o ID */
function falalouco_setup_categoria($nome, $slug, $pai) {
    $existente = get_term_by('slug', $slug, 'category');
    if ($existente) {
        return (int) $existente->term_id;
    }
    $resultado = wp_insert_term($nome, 'category', array('slug' => $slug, 'parent' => $pai));
    if (is_wp_error($resultado)) {
        return 0;
    }
    return (int) $resultado['term_id'];
}

/* Cria uma página se ela ainda não existir e devolve o ID */
function falalouco_setup_pagina($titulo, $slug, $conteudo, $status) {
    $existente = get_page_by_path($slug);
    if ($existente) {
        return (int) $existente->ID;
    }
    $id = wp_insert_post(array(
        'post_title'   => $titulo,
        'post_name'    => $slug,
        'post_content' => $conteudo,
        'post_status'  => $status,
        'post_type'    => 'page',
    ));
    if (is_wp_error($id)) {
        return 0;
    }
    return (int) $id;
}

/* Adiciona um item de página ao menu */
function falalouco_setup_item_pagina($menu_id, $titulo, $pagina_id) {
    if (!$pagina_id) {
        return;
    }
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title'     => $titulo,
        'menu-item-object'    => 'page',
        'menu-item-object-id' => $pagina_id,
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
    ));
}

/* Adiciona um item de categoria ao menu */
function falalouco_setup_item_categoria($menu_id, $titulo, $categoria_id) {
    if (!$categoria_id) {
        return;
    }
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title'     => $titulo,
        'menu-item-object'    => 'category',
        'menu-item-object-id' => $categoria_id,
        'menu-item-type'      => 'taxonomy',
        'menu-item-status'    => 'publish',
    ));
}

/* Rotina principal: roda na ativação do plugin */
function falalouco_setup_executar() {
    global $wp_rewrite;

    if (get_option('falalouco_setup_feito')) {
        return;
    }

    /* 1) Ajustes gerais */
    update_option('blogname', 'Fala, Louco');
    update_option('blogdescription', 'Opinião, notícias e história do Cruzeiro, feitas por um torcedor');
    update_option('timezone_string', 'America/Sao_Paulo');
    update_option('date_format', 'd/m/Y');
    update_option('time_format', 'H:i');
    update_option('start_of_week', 0);
    /* Mantém o site escondido do Google até o lançamento */
    update_option('blog_public', 0);
    /* Endereços bonitos */
    $wp_rewrite->set_permalink_structure('/%postname%/');

    /* 2) Categorias */
    $noticias = falalouco_setup_categoria('Notícias', 'noticias', 0);
    falalouco_setup_categoria('Últimas', 'ultimas', $noticias);
    falalouco_setup_categoria('Mercado da bola', 'mercado-da-bola', $noticias);
    falalouco_setup_categoria('Bastidores', 'bastidores', $noticias);
    falalouco_setup_categoria('Base', 'base', $noticias);
    falalouco_setup_categoria('Feminino', 'feminino', $noticias);

    $opiniao = falalouco_setup_categoria('Opinião', 'opiniao', 0);
    falalouco_setup_categoria('Pós-jogo', 'pos-jogo', $opiniao);
    falalouco_setup_categoria('Bate-pronto', 'bate-pronto', $opiniao);
    falalouco_setup_categoria('Resenha', 'resenha', $opiniao);

    /* 3) Páginas base */
    $inicio = falalouco_setup_pagina(
        'Início',
        'inicio',
        '<p>Em construção. Aqui vai a página inicial: últimas notícias, Hoje na História e destaques da Opinião.</p>',
        'publish'
    );
    $idolos = falalouco_setup_pagina(
        'Ídolos',
        'idolos',
        '<p>Em breve: fichas dos craques, organizadas por décadas.</p>',
        'publish'
    );
    $conquistas = falalouco_setup_pagina(
        'Conquistas',
        'conquistas',
        '<p>Em breve: títulos estaduais, nacionais, sul-americanos e internacionais.</p>',
        'publish'
    );
    $dia_de_jogo = falalouco_setup_pagina(
        'Dia de Jogo',
        'dia-de-jogo',
        '<p>Em breve: antes, durante e depois de cada jogo.</p>',
        'publish'
    );
    $sobre = falalouco_setup_pagina(
        'Sobre',
        'sobre',
        '<p><strong>Fala, Louco</strong> é um site de torcedor, sem vínculo com o Cruzeiro Esporte Clube. Nome, escudo e marcas do clube pertencem aos seus respectivos titulares.</p><p>As notícias são resumidas com palavras próprias, sempre com crédito e link da fonte original.</p>',
        'publish'
    );
    falalouco_setup_pagina(
        'Contato',
        'contato',
        '<p>O formulário de contato será colocado aqui.</p>',
        'publish'
    );

    /* Política de privacidade: só cria se o WordPress ainda não tiver uma. Fica em rascunho. */
    if (!get_option('wp_page_for_privacy_policy')) {
        $privacidade = falalouco_setup_pagina(
            'Política de Privacidade',
            'politica-de-privacidade',
            '<p>RASCUNHO: texto a ser escrito e revisado antes do lançamento e antes de abrir cadastro de usuários (LGPD).</p>',
            'draft'
        );
        if ($privacidade) {
            update_option('wp_page_for_privacy_policy', $privacidade);
        }
    }

    /* Página inicial fixa */
    if ($inicio) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $inicio);
    }

    /* 4) Lixeira para o conteúdo de exemplo do WordPress (dá para restaurar) */
    $ola = get_page_by_path('ola-mundo', OBJECT, 'post');
    if (!$ola) {
        $ola = get_page_by_path('hello-world', OBJECT, 'post');
    }
    if ($ola) {
        wp_trash_post($ola->ID);
    }
    $exemplo = get_page_by_path('pagina-exemplo');
    if (!$exemplo) {
        $exemplo = get_page_by_path('sample-page');
    }
    if ($exemplo) {
        wp_trash_post($exemplo->ID);
    }

    /* 5) Menu principal */
    $nome_menu = 'Menu principal';
    $menu = wp_get_nav_menu_object($nome_menu);
    if ($menu) {
        $menu_id = (int) $menu->term_id;
    } else {
        $menu_id = wp_create_nav_menu($nome_menu);
    }

    if (!is_wp_error($menu_id) && $menu_id) {
        $itens_atuais = wp_get_nav_menu_items($menu_id);
        if (empty($itens_atuais)) {
            falalouco_setup_item_pagina($menu_id, 'Início', $inicio);
            falalouco_setup_item_categoria($menu_id, 'Notícias', $noticias);
            falalouco_setup_item_categoria($menu_id, 'Opinião', $opiniao);
            falalouco_setup_item_pagina($menu_id, 'Ídolos', $idolos);
            falalouco_setup_item_pagina($menu_id, 'Conquistas', $conquistas);
            falalouco_setup_item_pagina($menu_id, 'Dia de Jogo', $dia_de_jogo);
            falalouco_setup_item_pagina($menu_id, 'Sobre', $sobre);
        }
        /* Liga o menu ao local "primary" (menu principal do Astra) */
        $locais = get_theme_mod('nav_menu_locations');
        if (!is_array($locais)) {
            $locais = array();
        }
        $locais['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locais);
    }

    flush_rewrite_rules();
    update_option('falalouco_setup_feito', 1);
    update_option('falalouco_setup_aviso', 1);
}
register_activation_hook(__FILE__, 'falalouco_setup_executar');

/* Aviso no painel depois da configuração */
function falalouco_setup_aviso() {
    if (!get_option('falalouco_setup_aviso')) {
        return;
    }
    delete_option('falalouco_setup_aviso');
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo '<strong>Fala, Louco:</strong> configuração inicial concluída (ajustes, categorias, páginas e menu). ';
    echo 'O site continua escondido do Google. Agora você pode desativar e excluir este plugin.';
    echo '</p></div>';
}
add_action('admin_notices', 'falalouco_setup_aviso');
