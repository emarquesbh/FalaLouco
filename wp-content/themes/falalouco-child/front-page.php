<?php
/**
 * Arquivo: front-page.php do tema filho Fala, Louco.
 * Caminho: wp-content/themes/falalouco-child/front-page.php
 * O que faz: monta a página inicial com quatro blocos: boas-vindas,
 * Hoje na História, Últimas notícias e Destaques da Opinião.
 * O texto da página "Início" no painel não é usado aqui.
 * As funções dos blocos ficam em inc/home.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area primary fl-home-area">
    <main id="main" class="site-main fl-home">
        <?php
        fl_home_boas_vindas();
        fl_home_hoje();
        fl_home_noticias();
        fl_home_opiniao();
        ?>
    </main>
</div>

<?php
get_footer();
