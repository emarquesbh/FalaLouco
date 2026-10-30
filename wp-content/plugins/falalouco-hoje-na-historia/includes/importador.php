<?php
/**
 * Arquivo: importador.php do plugin Hoje na História.
 * Caminho: wp-content/plugins/falalouco-hoje-na-historia/includes/importador.php
 * O que faz: cria a página "Importar planilha" (menu Hoje na História) e
 * importa fatos de um arquivo CSV. Tudo entra como RASCUNHO, para revisão.
 * Aceita separador ; (Excel brasileiro) ou , (Google Planilhas).
 * Compatível com PHP 7.2+.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Colunas do modelo, na ordem */
function flhh_colunas_modelo() {
    return array(
        'dia', 'mes', 'ano', 'titulo', 'texto', 'categoria',
        'fonte1_nome', 'fonte1_url', 'fonte2_nome', 'fonte2_url', 'credito_imagem',
    );
}

/* Menu: Hoje na História > Importar planilha */
function flhh_importador_menu() {
    add_submenu_page(
        'edit.php?post_type=fato_historico',
        'Importar planilha',
        'Importar planilha',
        'manage_options',
        'flhh-importar',
        'flhh_importador_pagina'
    );
}
add_action('admin_menu', 'flhh_importador_menu');

/* Baixa o modelo de planilha (CSV com ponto e vírgula, abre certo no Excel) */
function flhh_importador_baixar_modelo() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão.');
    }
    check_admin_referer('flhh_modelo');
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modelo-hoje-na-historia.csv"');
    echo "\xEF\xBB\xBF";
    echo implode(';', flhh_colunas_modelo()) . "\r\n";
    echo '1;1;2000;EXEMPLO - apague esta linha;Escreva aqui o texto do fato com palavras próprias.;Títulos;Nome da fonte 1;https://exemplo.com/1;Nome da fonte 2;https://exemplo.com/2;Autor / acervo livre' . "\r\n";
    exit;
}
add_action('admin_post_flhh_modelo', 'flhh_importador_baixar_modelo');

/* Padroniza nomes de colunas: sem acento, minúsculas, com _ */
function flhh_norm($texto) {
    $texto = strtolower(remove_accents(trim($texto)));
    $texto = preg_replace('/[^a-z0-9]+/', '_', $texto);
    return trim($texto, '_');
}

/* Lê o mês como número (1 a 12) ou por nome (janeiro, março...) */
function flhh_importador_mes($valor) {
    $valor = trim($valor);
    if ($valor === '') {
        return 0;
    }
    if (ctype_digit($valor)) {
        return (int) $valor;
    }
    $alvo = flhh_norm($valor);
    foreach (flhh_nomes_meses() as $numero => $nome) {
        if (flhh_norm($nome) === $alvo) {
            return (int) $numero;
        }
    }
    return 0;
}

/* Pega o valor de uma coluna da linha */
function flhh_campo($campos, $mapa, $nome) {
    if (!isset($mapa[$nome])) {
        return '';
    }
    $i = $mapa[$nome];
    return isset($campos[$i]) ? trim($campos[$i]) : '';
}

/* Já existe um fato com o mesmo título e a mesma data? */
function flhh_importador_existe($titulo, $dia, $mes) {
    $ids = get_posts(array(
        'post_type'      => 'fato_historico',
        'post_status'    => 'any',
        'title'          => $titulo,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => '_flhh_dia', 'value' => $dia, 'type' => 'NUMERIC'),
            array('key' => '_flhh_mes', 'value' => $mes, 'type' => 'NUMERIC'),
        ),
    ));
    return !empty($ids);
}

/* Lê o texto do CSV e cria os rascunhos. Devolve um relatório. */
function flhh_importador_processar($conteudo) {
    $resultado = array('importados' => 0, 'ignorados' => 0, 'avisos' => array(), 'erro' => '');

    /* Remove o marcador BOM e acerta a codificação (Excel antigo salva em Windows-1252) */
    $conteudo = str_replace("\xEF\xBB\xBF", '', $conteudo);
    if (!preg_match('//u', $conteudo)) {
        if (function_exists('mb_convert_encoding')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        } else {
            $conteudo = utf8_encode($conteudo);
        }
    }

    /* Descobre o separador pela primeira linha */
    $pos = strpos($conteudo, "\n");
    $primeira = ($pos === false) ? $conteudo : substr($conteudo, 0, $pos);
    $sep = (substr_count($primeira, ';') >= substr_count($primeira, ',')) ? ';' : ',';

    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $conteudo);
    rewind($fp);

    $cabecalho = fgetcsv($fp, 0, $sep);
    if (!$cabecalho) {
        fclose($fp);
        $resultado['erro'] = 'O arquivo está vazio ou não é uma planilha CSV válida.';
        return $resultado;
    }
    $mapa = array();
    foreach ($cabecalho as $i => $nome) {
        $mapa[flhh_norm($nome)] = $i;
    }
    foreach (array('dia', 'mes', 'ano', 'titulo', 'texto') as $obrigatoria) {
        if (!isset($mapa[$obrigatoria])) {
            fclose($fp);
            $resultado['erro'] = 'Falta a coluna "' . $obrigatoria . '" na primeira linha da planilha. Use o modelo.';
            return $resultado;
        }
    }

    $linha = 1;
    $total = 0;
    while (($campos = fgetcsv($fp, 0, $sep)) !== false) {
        $linha++;
        if (count($campos) === 1 && trim($campos[0]) === '') {
            continue;
        }
        $total++;
        if ($total > 300) {
            $resultado['avisos'][] = 'Limite de 300 linhas por arquivo: o restante foi deixado de fora. Importe o resto em outro arquivo.';
            break;
        }

        $titulo = sanitize_text_field(flhh_campo($campos, $mapa, 'titulo'));
        if ($titulo === '') {
            $resultado['ignorados']++;
            $resultado['avisos'][] = 'Linha ' . $linha . ': ignorada, sem título.';
            continue;
        }
        if (stripos($titulo, 'EXEMPLO') === 0) {
            $resultado['ignorados']++;
            $resultado['avisos'][] = 'Linha ' . $linha . ': linha de exemplo do modelo, ignorada.';
            continue;
        }

        $dia = absint(flhh_campo($campos, $mapa, 'dia'));
        $mes = flhh_importador_mes(flhh_campo($campos, $mapa, 'mes'));
        $ano = absint(flhh_campo($campos, $mapa, 'ano'));
        if (!flhh_data_valida($dia, $mes, $ano)) {
            $resultado['ignorados']++;
            $resultado['avisos'][] = 'Linha ' . $linha . ' (' . $titulo . '): ignorada, a data não existe ou está incompleta.';
            continue;
        }

        if (flhh_importador_existe($titulo, $dia, $mes)) {
            $resultado['ignorados']++;
            $resultado['avisos'][] = 'Linha ' . $linha . ' (' . $titulo . '): ignorada, já existe um fato com esse título e essa data.';
            continue;
        }

        $texto = wp_kses_post(flhh_campo($campos, $mapa, 'texto'));
        $f1n = sanitize_text_field(flhh_campo($campos, $mapa, 'fonte1_nome'));
        $f1u = esc_url_raw(flhh_campo($campos, $mapa, 'fonte1_url'), array('http', 'https'));
        $f2n = sanitize_text_field(flhh_campo($campos, $mapa, 'fonte2_nome'));
        $f2u = esc_url_raw(flhh_campo($campos, $mapa, 'fonte2_url'), array('http', 'https'));
        $cred = sanitize_text_field(flhh_campo($campos, $mapa, 'credito_imagem'));
        $categoria = sanitize_text_field(flhh_campo($campos, $mapa, 'categoria'));

        $id = wp_insert_post(array(
            'post_type'    => 'fato_historico',
            'post_status'  => 'draft',
            'post_title'   => wp_slash($titulo),
            'post_content' => wp_slash($texto),
        ));
        if (!$id || is_wp_error($id)) {
            $resultado['ignorados']++;
            $resultado['avisos'][] = 'Linha ' . $linha . ' (' . $titulo . '): não foi possível criar o rascunho.';
            continue;
        }

        update_post_meta($id, '_flhh_dia', $dia);
        update_post_meta($id, '_flhh_mes', $mes);
        update_post_meta($id, '_flhh_ano', $ano);
        update_post_meta($id, '_flhh_fonte1_nome', $f1n);
        update_post_meta($id, '_flhh_fonte1_url', $f1u);
        update_post_meta($id, '_flhh_fonte2_nome', $f2n);
        update_post_meta($id, '_flhh_fonte2_url', $f2u);
        update_post_meta($id, '_flhh_credito_imagem', $cred);

        if ($categoria !== '') {
            $termo = get_term_by('name', $categoria, 'categoria_fato');
            if (!$termo) {
                $termo = get_term_by('slug', sanitize_title($categoria), 'categoria_fato');
            }
            if ($termo) {
                wp_set_object_terms($id, (int) $termo->term_id, 'categoria_fato');
            } else {
                $resultado['avisos'][] = 'Linha ' . $linha . ' (' . $titulo . '): a categoria "' . $categoria . '" não existe; o fato ficou sem categoria.';
            }
        }

        if ($f1n === '' || $f1u === '' || $f2n === '' || $f2u === '') {
            $resultado['avisos'][] = 'Linha ' . $linha . ' (' . $titulo . '): importado, mas faltam fontes (nome e link das duas). Complete antes de publicar.';
        }
        $resultado['importados']++;
    }
    fclose($fp);
    return $resultado;
}

/* Página do painel */
function flhh_importador_pagina() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão.');
    }

    $resultado = null;
    $erro_envio = '';

    if (isset($_POST['flhh_imp_enviar'])) {
        check_admin_referer('flhh_importar', 'flhh_imp_nonce');
        if (empty($_FILES['flhh_arquivo']['tmp_name']) || !is_uploaded_file($_FILES['flhh_arquivo']['tmp_name'])) {
            $erro_envio = 'Escolha um arquivo CSV antes de clicar em Importar.';
        } else {
            $nome_arquivo = isset($_FILES['flhh_arquivo']['name']) ? sanitize_file_name($_FILES['flhh_arquivo']['name']) : '';
            $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
            $tamanho = isset($_FILES['flhh_arquivo']['size']) ? (int) $_FILES['flhh_arquivo']['size'] : 0;
            if (!in_array($extensao, array('csv', 'txt'), true)) {
                $erro_envio = 'O arquivo precisa ser .csv (no Excel: Salvar como, "CSV (separado por vírgulas)" ou "CSV UTF-8").';
            } elseif ($tamanho > 2 * 1024 * 1024) {
                $erro_envio = 'O arquivo é grande demais (limite de 2 MB).';
            } else {
                $conteudo = file_get_contents($_FILES['flhh_arquivo']['tmp_name']);
                $resultado = flhh_importador_processar($conteudo);
            }
        }
    }

    $url_modelo = wp_nonce_url(admin_url('admin-post.php?action=flhh_modelo'), 'flhh_modelo');
    $url_rascunhos = admin_url('edit.php?post_status=draft&post_type=fato_historico');

    echo '<div class="wrap">';
    echo '<h1>Importar planilha: Hoje na História</h1>';

    if ($erro_envio !== '') {
        echo '<div class="notice notice-error"><p>' . esc_html($erro_envio) . '</p></div>';
    }
    if (is_array($resultado)) {
        if ($resultado['erro'] !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($resultado['erro']) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p><strong>' . (int) $resultado['importados'] . '</strong> fato(s) importado(s) como rascunho';
            echo ' e <strong>' . (int) $resultado['ignorados'] . '</strong> linha(s) ignorada(s). ';
            echo '<a href="' . esc_url($url_rascunhos) . '">Ver os rascunhos</a></p></div>';
            if (!empty($resultado['avisos'])) {
                echo '<div class="notice notice-warning"><p><strong>Avisos:</strong></p><ul style="list-style:disc;margin-left:24px;">';
                $mostrados = 0;
                foreach ($resultado['avisos'] as $aviso) {
                    if ($mostrados >= 100) {
                        echo '<li>... e mais avisos não mostrados.</li>';
                        break;
                    }
                    echo '<li>' . esc_html($aviso) . '</li>';
                    $mostrados++;
                }
                echo '</ul></div>';
            }
        }
    }

    echo '<h2>Como funciona</h2><ol>';
    echo '<li>Baixe o <a href="' . esc_url($url_modelo) . '">modelo de planilha</a> e abra no Excel ou no Google Planilhas.</li>';
    echo '<li>Preencha uma linha por fato. Apague a linha de exemplo. Colunas: dia, mes (número ou nome), ano, titulo, texto, categoria, fonte1_nome, fonte1_url, fonte2_nome, fonte2_url, credito_imagem.</li>';
    echo '<li>Salve como CSV e envie abaixo.</li>';
    echo '<li>Tudo entra como <strong>rascunho</strong>. Revise, coloque a imagem destacada (se tiver) e publique um a um ou em lote.</li>';
    echo '</ol>';
    echo '<p>Linhas com data inexistente ou sem título são ignoradas. Fatos repetidos (mesmo título e mesma data) também. Máximo de 300 linhas por arquivo. Escreva os textos com palavras próprias e confira cada informação antes de publicar.</p>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('flhh_importar', 'flhh_imp_nonce');
    echo '<p><input type="file" name="flhh_arquivo" accept=".csv,.txt"></p>';
    echo '<p><button type="submit" name="flhh_imp_enviar" value="1" class="button button-primary">Importar</button></p>';
    echo '</form>';
    echo '</div>';
}
