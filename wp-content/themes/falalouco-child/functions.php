<?php
/**
 * Arquivo: functions.php do tema filho Fala, Louco.
 * Caminho: wp-content/themes/falalouco-child/functions.php
 * O que faz: carrega o CSS do Astra (pai) e do tema filho, e mostra
 * no rodapé o aviso de que o site é de torcedor e não é oficial.
 * Também carrega o visual da seção Opinião (inc/opiniao.php).
 * Compatível com PHP 7.2+.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Visual da seção Opinião e modelos de texto do editor */
require_once get_stylesheet_directory() . '/inc/opiniao.php';
require_once get_stylesheet_directory() . '/inc/modelos-opiniao.php';

/* Página inicial (blocos usados pelo front-page.php) */
require_once get_stylesheet_directory() . '/inc/home.php';

/* Carrega os estilos: primeiro o do Astra, depois o do tema filho */
function falalouco_child_estilos() {
    $versao_filho = wp_get_theme()->get('Version');
    wp_enqueue_style(
        'astra-pai',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme('astra')->get('Version')
    );
    wp_enqueue_style(
        'falalouco-filho',
        get_stylesheet_uri(),
        array('astra-pai'),
        $versao_filho
    );
}
add_action('wp_enqueue_scripts', 'falalouco_child_estilos');

/* Aviso de site não oficial, impresso no fim de todas as páginas */
function falalouco_child_aviso_rodape() {
    echo '<div class="fl-aviso-nao-oficial">';
    echo '<strong>Fala, Louco</strong> é um site de torcedor, sem vínculo com o Cruzeiro Esporte Clube. ';
    echo 'Nome, escudo e marcas do clube pertencem aos seus respectivos titulares.';
    echo '</div>';
}
add_action('wp_footer', 'falalouco_child_aviso_rodape');
