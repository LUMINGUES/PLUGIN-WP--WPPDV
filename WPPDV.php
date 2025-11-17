/**
 * Plugin Name: WPPDV
 * Description: Sistema de Ponto de Venda com módulos de produtos, estoque, relatórios, cadastro de clientes e agora com infraestrutura de Pré-Faturamento Fiscal (NF-e).
 * Version: 1.6.1
 * Author: Lumingues 
 * License: GPL-2.0+
 * Text Domain: WPPDV
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica se o WP Customer CRM está ativo e define a constante da tabela.
// Se não estiver ativo, a integração de clientes será limitada.
if (defined('WPCRM_TABLE')) {
    define('WPDDV_CRM_TABLE', WPCRM_TABLE);
} else {
    define('WPDDV_CRM_TABLE', $GLOBALS['wpdb']->prefix . 'wpcrm_customers'); // Define o nome da tabela do CRM
}


// =================================================================================
// 0. FUNÇÃO DE VERIFICAÇÃO DE WOOCOMMERCE
// =================================================================================

function mpdm_is_woocommerce_active() {
    // Checa se o WooCommerce está ativo na lista de plugins
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    return is_plugin_active('woocommerce/woocommerce.php');
}

// =================================================================================
// 1. INCLUSÃO DE SCRIPTS E ESTILOS
// =================================================================================

if (!function_exists('mpdm_enqueue_scripts')) {
    add_action('admin_enqueue_scripts', 'mpdm_enqueue_scripts');
    function mpdm_enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_meu-pdv' && $hook !== 'meu-pdv_page_meu-pdv-settings' && $hook !== 'meu-pdv_page_meu-pdv-reports' && $hook !== 'meu-pdv_page_meu-pdv-customers' && $hook !== 'meu-pdv_page_meu-pdv-birthdays' && $hook !== 'meu-pdv_page_meu-pdv-fiscal') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jsbarcode', 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js', [], '3.11.5', true);
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
        wp_enqueue_script('jquery');
        
        wp_localize_script('jquery', 'mpdm_ajax', array(
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mpdm-pos-nonce'),
            'zoom'     => get_user_meta(get_current_user_id(), 'mpdm_zoom_level', true) ?: 1.0,
            'pix_key'  => get_option('mpdm_pix_key', ''),
            'pix_name' => get_option('mpdm_company_name', ''),
            'pix_city' => 'Sao Paulo', // Hardcoded no original, mantido.
            'pix_cep'  => '00000000'    // Hardcoded no original, mantido.
        ));

        wp_add_inline_style('wp-admin', '
             /* =================================================================================
                Estilos Gerais do Plugin
                ================================================================================= */
            .mpdm-content h2, .mpdm-content h3 {
                font-size: 1.5em;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-top: 20px;
                color: #333;
            }
            .mpdm-content form {
                background-color: #fff;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                margin-top: 20px;
            }
            .mpdm-content label {
                font-weight: 600;
                display: block;
                margin-top: 15px;
                color: #555;
            }
            .mpdm-content input[type="text"], 
            .mpdm-content input[type="number"], 
            .mpdm-content input[type="email"],
            .mpdm-content textarea, 
            .mpdm-content select,
            .mpdm-content input[type="date"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                box-sizing: border-box;
                font-size: 14px;
                transition: all 0.3s;
            }
            .mpdm-content input[type="text"]:focus, 
            .mpdm-content input[type="number"]:focus, 
            .mpdm-content input[type="email"]:focus,
            .mpdm-content textarea:focus, 
            .mpdm-content select:focus,
            .mpdm-content input[type="date"]:focus {
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.2);
                outline: none;
            }
            .mpdm-content input[type="submit"], .mpdm-content .button {
                background-color: #0073aa;
                color: #fff;
                border: none;
                padding: 12px 20px;
                font-weight: bold;
                cursor: pointer;
                border-radius: 6px;
                transition: background-color 0.3s;
            }
            .mpdm-content input[type="submit"]:hover, .mpdm-content .button:hover {
                background-color: #005177;
            }
            .mpdm-content hr {
                border-color: #f0f0f0;
                margin: 30px 0;
            }
            .mpdm-content .tablenav {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
            }
            .mpdm-content .search-box {
                margin: 0;
            }
            .mpdm-content .tablenav-pages {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .mpdm-content .tablenav-pages a {
                text-decoration: none;
                padding: 5px 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .mpdm-content .tablenav-pages .page-numbers.current {
                background-color: #0073aa;
                color: #fff;
                border-color: #0073aa;
            }
            .mpdm-content .wp-list-table {
                border-collapse: collapse;
                width: 100%;
            }
            .mpdm-content .wp-list-table th, .mpdm-content .wp-list-table td {
                padding: 12px;
            }
            .mpdm-content .variation-row {
                background-color: #f7f7f7;
            }
            .mpdm-content .item-details {
                padding: 10px;
                background-color: #fcfcfc;
                border: 1px solid #eee;
                border-radius: 4px;
                margin-top: 5px;
            }
            .mpdm-content .item-details .detail-item {
                font-size: 0.9em;
            }

            .company-logo {
                max-width: 150px;
                height: auto;
                display: block;
                margin: 0 auto 20px;
            }
            .company-info {
                text-align: center;
                margin-bottom: 20px;
            }

            @media print {
                body, html {
                    margin: 0 !important;
                    padding: 0 !important;
                    text-align: left !important;
                }
                body * { visibility: hidden; }
                #pos-receipt-print-content, #pos-receipt-print-content * { visibility: visible; }
                #pos-receipt-print-content {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 80mm;
                    font-family: monospace;
                    font-size: 12px;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                #pos-receipt-print-content pre {
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    margin: 0 !important;
                    padding: 10px !important;
                }
                .barcode-print-svg {
                    display: none;
                }
                h3 {
                    text-align: left !important;
                    font-size: 16px;
                    margin: 5px 0;
                }
                /* NOVO: Esconde a lixeira na hora de imprimir */
                .delete-item-btn {
                    display: none !important;
                }
            }
            
            /* ESTILOS DE POPUP PIX */
            .mpdm-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
                display: none;
                justify-content: center;
                align-items: center;
            }

            .mpdm-modal-content {
                background-color: #fefefe;
                margin: auto;
                padding: 30px;
                border: 1px solid #888;
                width: 90%;
                max-width: 400px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.5);
                position: relative;
                text-align: center;
            }
            .mpdm-modal-content h2 {
                text-align: center;
                margin-top: 0;
                font-size: 1.5em;
            }
            .mpdm-modal-content .close {
                color: #aaa;
                font-size: 28px;
                font-weight: bold;
                position: absolute;
                top: 10px;
                right: 20px;
                cursor: pointer;
            }
            .mpdm-modal-content .close:hover,
            .mpdm-modal-content .close:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            }
            .mpdm-modal-content #pix-qr-code {
                margin: 20px auto;
                width: 200px;
                height: 200px;
            }
            .mpdm-modal-content .modal-buttons {
                margin-top: 20px;
            }

            /* ESTILOS DE FRENTE DE CAIXA COMPACTOS */
            #pos-container {
                position: relative;
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 15px;
                margin-top: 15px;
                font-family: "Segoe UI", Arial, sans-serif;
                background: #f0f4f7;
                padding: 15px;
                border-radius: 12px;
                box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
                min-height: 85vh;
            }
            #pos-left {
                background: #ffffff;
                padding: 15px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                border: 1px solid #e0e0e0;
                display: flex;
                flex-direction: column;
            }
            #pos-right {
                background: #ffffff;
                padding: 15px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                border: 1px solid #e0e0e0;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .input-group {
                display: flex;
                align-items: center;
                gap: 5px;
                margin-bottom: 10px;
            }
            #pos-identifier-input, #customer-cpf-input {
                flex-grow: 1;
                padding: 8px;
                font-size: 0.9em;
                border: 1px solid #ccc;
                border-radius: 6px;
                transition: all 0.2s ease;
            }
            #pos-identifier-input:focus, #customer-cpf-input:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
                outline: none;
            }
            #pos-quantity-multiplier {
                width: 50px;
                padding: 8px;
                text-align: center;
                font-size: 0.9em;
            }
            #add-unregistered-item-btn {
                background-color: #555;
                color: #fff;
                font-size: 0.9em;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 15px;
            }
            #pos-cart {
                list-style: none;
                padding: 0;
                max-height: 250px;
                overflow-y: auto;
                margin-top: 0;
                border-bottom: 1px dashed #e0e0e0;
                flex-grow: 1;
            }
            #pos-cart li {
                font-size: 1em;
                padding: 10px 0;
                border-bottom: 1px dashed #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 5px; /* Adicionado para separar o texto da lixeira */
            }
            #pos-cart li:last-child {
                border-bottom: none;
            }
            .delete-item-btn { /* Estilo do botão de lixeira */
                background: none;
                border: none;
                color: #e74c3c;
                cursor: pointer;
                font-size: 1.1em;
                padding: 0 5px;
                opacity: 0.8;
                transition: opacity 0.2s;
            }
            .delete-item-btn:hover {
                opacity: 1;
            }
            #receipt-summary-container {
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
            }
            .total-display {
                background: #2ecc71;
                color: #fff;
                padding: 15px;
                border-radius: 10px;
                text-align: center;
                margin-top: 15px;
                margin-bottom: 15px;
            }
            .total-display .label {
                font-size: 1em;
                opacity: 0.8;
                margin-bottom: 5px;
            }
            .total-display #total-value {
                font-size: 2.2em;
                font-weight: bold;
            }
            .payment-details-group {
                background: #fafafa;
                padding: 15px;
                border-radius: 8px;
                margin-top: 15px;
                border: 1px solid #f0f0f0;
            }
            .payment-details-group select, 
            .payment-details-group input {
                padding: 8px;
                border-radius: 6px;
                font-size: 0.9em;
            }
            #change-display {
                font-size: 1.5em;
                margin-top: 10px;
                font-weight: bold;
            }
            #change-value {
                color: #000;
            }
            #change-value.negative {
                color: #e74c3c;
            }
            #discount-info {
                margin-top: 10px;
                font-size: 0.9em;
                color: #555;
            }
            #customer-info {
                margin-top: 10px;
                font-size: 0.9em;
                color: #555;
            }
            .mpdm-form-buttons {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-top: 15px;
            }
            .mpdm-form-buttons button {
                padding: 12px;
                font-size: 1em;
                border-radius: 8px;
            }

            /* Estilos para a página de clientes, aniversariantes e configurações */
            .mpdm-settings-container, .mpdm-customer-container, .mpdm-birthdays-container, .mpdm-fiscal-container {
                background: #f0f4f7;
                padding: 20px;
                border-radius: 12px;
            }
            .mpdm-settings-section, .mpdm-customer-section {
                background: #ffffff;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                margin-bottom: 20px;
            }
            .mpdm-settings-section h2, .mpdm-customer-section h2 {
                font-size: 1.4em;
                color: #0073aa;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
                margin-top: 0;
            }
            .mpdm-settings-section label, .mpdm-customer-section label {
                margin-top: 10px;
                font-size: 1em;
            }
            .mpdm-settings-section .description, .mpdm-customer-section .description {
                font-style: italic;
                color: #888;
                margin-top: 5px;
            }
            .mpdm-customer-section.form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .mpdm-customer-section.form-grid .full-width {
                grid-column: 1 / -1;
            }

            /* Estilos para a lista de aniversariantes */
            .mpdm-birthdays-container .birthday-card {
                background: #fff;
                border-left: 5px solid #ffb74d;
                padding: 15px;
                margin-bottom: 10px;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .mpdm-birthdays-container .birthday-card strong {
                font-size: 1.1em;
            }
            .mpdm-birthdays-container .birthday-card p {
                margin: 5px 0 0;
                font-size: 0.9em;
                color: #666;
            }
            
            /* ESTILOS DE RELATÓRIOS */
            .mpdm-reports-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .mpdm-report-card {
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                padding: 20px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .mpdm-report-card.red { border-left: 5px solid #e74c3c; }
            .mpdm-report-card.green { border-left: 5px solid #2ecc71; }
            .mpdm-report-card.blue { border-left: 5px solid #3498db; }
            .mpdm-report-card.yellow { border-left: 5px solid #f1c40f; }

            .mpdm-simple-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .mpdm-simple-list li {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px dashed #eee;
            }
            
            .mpdm-simple-list li:last-child {
                border-bottom: none;
            }


            /* Estilos para o logo no PDV */
            .pos-company-logo {
                max-width: 120px;
                height: auto;
                display: block;
                margin: 0 auto 5px auto;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            /* Controles de navegação do PDV */
            .pos-controls {
                position: absolute;
                top: 25px;
                right: 25px;
                z-index: 10;
                display: flex;
                gap: 5px;
            }
            .pos-controls .button {
                width: 30px;
                height: 30px;
                min-width: 0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .pos-controls .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
                color: #555;
            }
            /* ESTILOS PARA O MODO TELA CHEIA (CUSTOMIZADO) */
            body.mpdm-fullscreen #wpadminbar {
                display: none;
            }
            body.mpdm-fullscreen #adminmenuwrap,
            body.mpdm-fullscreen #adminmenuback,
            body.mpdm-fullscreen #wpfooter,
            body.mpdm-fullscreen .nav-tab-wrapper {
                display: none;
            }
            body.mpdm-fullscreen #wpcontent,
            body.mpdm-fullscreen .wrap,
            body.mpdm-fullscreen #meu-pdv {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            body.mpdm-fullscreen h1.wp-ui-primary {
                display: none;
            }
        ');
    }
}

// =================================================================================
// 2. GESTÃO DOS MENUS E PÁGINAS NO PAINEL
// =================================================================================

if (!function_exists('mpdm_add_custom_caps')) {
    add_action('admin_init', 'mpdm_add_custom_caps');
    function mpdm_add_custom_caps() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('mpdm_manage_pos');
        }
        $role = get_role('editor');
        if ($role) {
            $role->add_cap('mpdm_manage_pos');
        }
    }
}

if (!function_exists('mpdm_add_admin_menu')) {
    add_action('admin_menu', 'mpdm_add_admin_menu');
    function mpdm_add_admin_menu() {
        add_menu_page(
            'Meu PDV',
            'Meu PDV',
            'mpdm_manage_pos',
            'meu-pdv',
            'mpdm_render_main_page',
            'dashicons-store',
            6
        );
        // O submenu Clientes agora aponta para o CRM, mas mantém a url para o WPPDV
        add_submenu_page(
            'meu-pdv',
            'Cadastro de Clientes',
            'Clientes',
            'mpdm_manage_pos',
            'meu-pdv-customers',
            'mpdm_render_customers_page'
        );
        add_submenu_page(
            'meu-pdv',
            'Aniversariantes',
            'Aniversariantes',
            'mpdm_manage_pos',
            'meu-pdv-birthdays',
            'mpdm_render_birthdays_page'
        );
        add_submenu_page(
            'meu-pdv',
            'Configurações',
            'Configurações',
            'mpdm_manage_pos',
            'meu-pdv-settings',
            'mpdm_render_settings_page'
        );
        add_submenu_page(
            'meu-pdv',
            'Relatórios',
            'Relatórios',
            'mpdm_manage_pos',
            'meu-pdv-reports',
            'mpdm_render_reports_page'
        );
        /* NOVO: Menu Fiscal/NFe */
        add_submenu_page(
            'meu-pdv',
            'Fiscal (NF-e)',
            'Fiscal (NF-e)',
            'mpdm_manage_pos',
            'meu-pdv-fiscal',
            'mpdm_render_fiscal_page'
        );
    }
}

if (!function_exists('mpdm_render_main_page')) {
    function mpdm_render_main_page() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        // Corrigido: Lógica de submissão tratada ANTES do HTML ser gerado.
        if (isset($_POST['action'])) {
            mpdm_handle_form_submissions();
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pos';

        ?>
        <div class="wrap">
            <h1>WPPDV</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=meu-pdv&tab=pos" class="nav-tab <?php echo $active_tab == 'pos' ? 'nav-tab-active' : ''; ?>">Frente de Caixa</a>
                <a href="?page=meu-pdv&tab=products" class="nav-tab <?php echo $active_tab == 'products' ? 'nav-tab-active' : ''; ?>">Cadastro de Produtos</a>
                <a href="?page=meu-pdv&tab=stock" class="nav-tab <?php echo $active_tab == 'stock' ? 'nav-tab-active' : ''; ?>">Entrada de Estoque</a>
                <a href="?page=meu-pdv&tab=history" class="nav-tab <?php echo $active_tab == 'history' ? 'nav-tab-active' : ''; ?>">Histórico de Vendas</a>
            </h2>
            <div id="meu-pdv" class="mpdm-content">
                <?php
                switch ($active_tab) {
                    case 'pos':
                        mpdm_render_pos_tab();
                        break;
                    case 'products':
                        mpdm_render_products_tab();
                        break;
                    case 'stock':
                        mpdm_render_stock_tab();
                        break;
                    case 'history':
                        mpdm_render_history_tab();
                        break;
                    default:
                        mpdm_render_pos_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
}

/**
 * REDIRECIONAMENTO DE CLIENTES PARA O CRM CENTRAL
 * @since 1.4.3
 */
if (!function_exists('mpdm_render_customers_page')) {
    function mpdm_render_customers_page() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        ?>
        <div class="wrap">
            <h1>Cadastro de Clientes</h1>
            <div class="notice notice-info">
                <p>
                    <strong>Atenção:</strong> O gerenciamento de clientes foi movido para o **CRM Central de Clientes** unificado.
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-customer-crm')); ?>" class="button button-primary">Ir para o CRM Central de Clientes</a>
                </p>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('mpdm_render_birthdays_page')) {
    function mpdm_render_birthdays_page() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        global $wpdb;
        $customers_table = WPDDV_CRM_TABLE; // Usando a tabela CRM unificada
        $current_month = date('m');

        // Note: Se WPDDV_CRM_TABLE for a tabela MPDM original, a lógica ainda funciona. 
        // Se for a WPCRM_TABLE, a lógica é a mesma, mas a tabela é diferente.
        $birthdays_sql = $wpdb->prepare("SELECT * FROM $customers_table WHERE MONTH(birthdate) = %d ORDER BY DAY(birthdate) ASC", $current_month);
        $birthdays = $wpdb->get_results($birthdays_sql);

        ?>
        <div class="wrap mpdm-birthdays-container">
            <h1>Aniversariantes do Mês</h1>
            <p>Clientes que fazem aniversário em **<?php echo date_i18n('F'); ?>**.</p>
            <p style="font-style: italic;">Dados puxados do CRM Central de Clientes (tabela: <?php echo WPDDV_CRM_TABLE; ?>).</p>
            <?php if (!empty($birthdays)) : ?>
                <?php foreach ($birthdays as $customer) : ?>
                    <div class="birthday-card">
                        <strong><?php echo esc_html($customer->name); ?></strong>
                        <p>Aniversário: <?php echo date_i18n('j \d\e F', strtotime($customer->birthdate)); ?></p>
                        <p>E-mail: <?php echo esc_html($customer->email); ?></p>
                        <p>CPF: <?php echo esc_html($customer->cpf); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>Nenhum cliente cadastrado fazendo aniversário neste mês.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mpdm_render_settings_page')) {
    function mpdm_render_settings_page() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        if (isset($_POST['mpdm_save_settings'])) {
            $pix_key = sanitize_text_field($_POST['mpdm_pix_key']);
            $company_name = sanitize_text_field($_POST['mpdm_company_name']);
            $company_address = sanitize_textarea_field($_POST['mpdm_company_address']);
            $company_phone = sanitize_text_field($_POST['mpdm_company_phone']);
            $company_logo = sanitize_url($_POST['mpdm_company_logo']);
            $expiration_days = intval($_POST['mpdm_expiration_days']);

            update_option('mpdm_pix_key', $pix_key);
            update_option('mpdm_company_name', $company_name);
            update_option('mpdm_company_address', $company_address);
            update_option('mpdm_company_phone', $company_phone);
            update_option('mpdm_company_logo', $company_logo);
            update_option('mpdm_expiration_days', $expiration_days);

            echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
        }
        
        $pix_key = get_option('mpdm_pix_key', '');
        $company_name = get_option('mpdm_company_name', '');
        $company_address = get_option('mpdm_company_address', '');
        $company_phone = get_option('mpdm_company_phone', '');
        $company_logo = get_option('mpdm_company_logo', '');
        $expiration_days = get_option('mpdm_expiration_days', 7);
        ?>
        <div class="wrap mpdm-settings-container">
            <h1>Configurações do PDV</h1>
            <form method="post">
                <input type="hidden" name="mpdm_save_settings" value="1">
                
                <div class="mpdm-settings-section">
                    <h2>Informações da Empresa</h2>
                    
                    <label for="mpdm_company_logo">Logo da Empresa:</label>
                    <div style="display:flex; align-items:flex-end; gap:10px; margin-bottom:15px;">
                        <input type="text" id="mpdm_company_logo" name="mpdm_company_logo" value="<?php echo esc_attr($company_logo); ?>" style="flex-grow:1;">
                        <button class="button" id="mpdm_upload_logo_btn">Carregar/Selecionar</button>
                    </div>
                    <div id="mpdm_logo_preview" style="margin-top:10px;">
                        <?php if (!empty($company_logo)) : ?>
                            <img src="<?php echo esc_url($company_logo); ?>" style="max-width:150px; height:auto;">
                        <?php endif; ?>
                    </div>
                    <p class="description">Faça o upload do logo da sua empresa.</p>
                    
                    <label for="mpdm_company_name">Nome da Empresa:</label>
                    <input type="text" id="mpdm_company_name" name="mpdm_company_name" value="<?php echo esc_attr($company_name); ?>">
                    
                    <label for="mpdm_company_address">Endereço da Empresa:</label>
                    <textarea id="mpdm_company_address" name="mpdm_company_address" rows="3"><?php echo esc_textarea($company_address); ?></textarea>

                    <label for="mpdm_company_phone">Telefone da Empresa:</label>
                    <input type="text" id="mpdm_company_phone" name="mpdm_company_phone" value="<?php echo esc_attr($company_phone); ?>">
                </div>

                <div class="mpdm-settings-section">
                    <h2>Configurações de Pagamento e Relatórios</h2>
                    <label for="mpdm_pix_key">Chave Pix:</label>
                    <input type="text" id="mpdm_pix_key" name="mpdm_pix_key" value="<?php echo esc_attr($pix_key); ?>">
                    <p class="description">Será exibida no recibo para pagamentos via Pix.</p>
                    
                    <label for="mpdm_expiration_days">Avisar produtos perto de vencer (dias):</label>
                    <input type="number" id="mpdm_expiration_days" name="mpdm_expiration_days" value="<?php echo esc_attr($expiration_days); ?>" min="1" max="365">
                    <p class="description">Defina quantos dias antes da data de validade um produto deve ser listado no relatório de vencimentos.</p>
                </div>
                
                <input type="submit" class="button button-primary" value="Salvar Configurações">
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var custom_uploader;
            $('#mpdm_upload_logo_btn').click(function(e) {
                e.preventDefault();
                if (custom_uploader) {
                    custom_uploader.open();
                    return;
                }
                custom_uploader = wp.media.frames.file_frame = wp.media({
                    title: 'Escolha o Logo',
                    button: {
                        text: 'Usar este Logo'
                    },
                    multiple: false
                });
                custom_uploader.on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#mpdm_company_logo').val(attachment.url);
                    $('#mpdm_logo_preview').html('<img src="' + attachment.url + '" style="max-width:150px; height:auto;">');
                });
                custom_uploader.open();
            });
        });
        </script>
        <?php
    }
}

if (!function_exists('mpdm_create_tables')) {
    add_action('admin_init', 'mpdm_create_tables');
    function mpdm_create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $products_table = $wpdb->prefix . 'mpdm_products';
        $sales_table = $wpdb->prefix . 'mpdm_sales';
        $customers_table = $wpdb->prefix . 'mpdm_customers';
        $customer_sales_table = $wpdb->prefix . 'mpdm_customer_sales';
        $fiscal_table = $wpdb->prefix . 'mpdm_fiscal'; // NOVO: Tabela Fiscal
        $charset_collate = $wpdb->get_charset_collate();

        // Tabela de Produtos (CRIADA APENAS SE WOOCOMMERCE NÃO ESTIVER ATIVO)
        if (!mpdm_is_woocommerce_active()) {
             $sql_products = "CREATE TABLE $products_table (
                 id mediumint(9) NOT NULL AUTO_INCREMENT,
                 name tinytext NOT NULL,
                 sku varchar(255) NOT NULL,
                 barcode varchar(255) NULL,
                 price decimal(10,2) NOT NULL,
                 quantity int(11) NOT NULL,
                 discount int(3) NOT NULL DEFAULT 0,
                 validity_date date NULL,
                 variations text NULL,
                 is_variable tinyint(1) NOT NULL DEFAULT 0,
                 UNIQUE KEY id (id),
                 UNIQUE KEY sku_unique (sku)
               ) $charset_collate;";
             dbDelta($sql_products);
             $existing_products_columns = $wpdb->get_col("DESCRIBE $products_table;");
             if (!in_array('discount', $existing_products_columns)) {
                 $wpdb->query("ALTER TABLE " . $products_table . " ADD discount int(3) NOT NULL DEFAULT 0 AFTER quantity");
             }
             if (!in_array('validity_date', $existing_products_columns)) {
                 $wpdb->query("ALTER TABLE " . $products_table . " ADD validity_date date NULL AFTER discount");
             }
             // NOVO: Colunas Fiscais para o DB Customizado
             if (!in_array('ncm', $existing_products_columns)) {
                 $wpdb->query("ALTER TABLE " . $products_table . " ADD ncm varchar(20) NULL AFTER barcode");
             }
             if (!in_array('cest', $existing_products_columns)) {
                 $wpdb->query("ALTER TABLE " . $products_table . " ADD cest varchar(20) NULL AFTER ncm");
             }
             if (!in_array('cfop', $existing_products_columns)) {
                 $wpdb->query("ALTER TABLE " . $products_table . " ADD cfop varchar(10) NULL AFTER cest");
             }
        }
        
        // Tabela de Vendas (principal)
        $sql_sales = "CREATE TABLE $sales_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sale_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,  /* GARANTIDO POR BOAS PRÁTICAS */
            payment_method varchar(20) NULL,
            customer_cpf varchar(20) NULL,
            items text NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
        dbDelta($sql_sales);
        $existing_sales_columns = $wpdb->get_col("DESCRIBE " . $sales_table);
        if (!in_array('customer_cpf', $existing_sales_columns)) {
            $wpdb->query("ALTER TABLE " . $sales_table . " ADD customer_cpf varchar(20) NULL AFTER payment_method");
        }
        if (!in_array('amount_paid', $existing_sales_columns)) {
            $wpdb->query("ALTER TABLE " . $sales_table . " ADD amount_paid decimal(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
        }
        // COLUNA WC_ORDER_ID ADICIONADA PARA RASTREAR PEDIDOS NO WOOCOMMERCE
        if (!in_array('wc_order_id', $existing_sales_columns)) {
            $wpdb->query("ALTER TABLE " . $sales_table . " ADD wc_order_id mediumint(9) NULL AFTER customer_cpf");
        }

        /* NOVO: Tabela de Dados Fiscais/Pré-Faturamento */
        $sql_fiscal = "CREATE TABLE $fiscal_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sale_id mediumint(9) NOT NULL, /* Link para a venda do PDV */
            sale_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_cpf varchar(20) NULL,
            total_amount decimal(10,2) NOT NULL,
            items_fiscal text NOT NULL, /* Dados detalhados dos itens para NF-e */
            nfe_status varchar(50) NOT NULL DEFAULT 'pendente', /* Status de emissão: pendente, enviada, concluida, erro */
            nfe_id varchar(255) NULL, /* ID de rastreamento do NFE.io */
            nfe_json text NULL, /* JSON completo enviado ao NFE.io (para auditoria) */
            UNIQUE KEY id (id),
            KEY sale_id (sale_id)
        ) $charset_collate;";
        dbDelta($sql_fiscal);


        // Tabela de Clientes - Mantida apenas para SINCRONIZAÇÃO (Legacy/Source)
        $sql_customers = "CREATE TABLE $customers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cpf varchar(20) NOT NULL,
            name tinytext NOT NULL,
            email varchar(100) NULL,
            birthdate date NULL,
            sex varchar(20) NULL,
            address text NULL,
            discount int(3) NOT NULL DEFAULT 0,
            registration_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE KEY id (id),
            UNIQUE KEY cpf_unique (cpf)
        ) $charset_collate;";
        dbDelta($sql_customers);
        $existing_customer_columns = $wpdb->get_col("DESCRIBE $customers_table;");
        if (!in_array('email', $existing_customer_columns)) {
            $wpdb->query("ALTER TABLE " . $customers_table . " ADD email varchar(100) NULL AFTER name");
        }

        // Tabela de Histórico de Vendas por Cliente (para consulta rápida) - Mantida para o Histórico de Vendas PDV
        $sql_customer_sales = "CREATE TABLE $customer_sales_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_cpf varchar(20) NOT NULL,
            sale_id mediumint(9) NOT NULL,
            sale_date datetime NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            items text NOT NULL,
            UNIQUE KEY id (id)
            /* FOREIGN KEY (customer_cpf) REFERENCES " . $customers_table . "(cpf) ON DELETE CASCADE, // Removendo FK para evitar falhas de sincronização */
            /* FOREIGN KEY (sale_id) REFERENCES " . $sales_table . "(id) ON DELETE CASCADE // Removendo FK para evitar falhas de sincronização */
        ) $charset_collate;";
        dbDelta($sql_customer_sales);
    }
}

if (!function_exists('mpdm_render_products_tab')) {
    function mpdm_render_products_tab() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mpdm_products';
        $use_woocommerce = mpdm_is_woocommerce_active();

        // Funções auxiliares para dados de variação do WC
        function mpdm_get_wc_variations_data($product_wc) {
            $variations_data = [];
            if ($product_wc->is_type('variable')) {
                foreach ($product_wc->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);
                    if (!$variation) continue;

                    $attributes = $variation->get_variation_attributes();
                    $name = $product_wc->get_name() . ' - ' . implode(', ', $attributes);
                    
                    $variations_data[] = [
                        'name' => $name,
                        'sku' => $variation->get_sku(),
                        'barcode' => $variation->get_meta('_barcode_field') ?: '',
                        'price' => $variation->get_price(),
                        'discount' => $variation->get_meta('_pdv_discount') ?: 0,
                        'quantity' => $variation->get_stock_quantity(),
                    ];
                }
            }
            return json_encode($variations_data);
        }

        // Lógica de edição e exclusão
        $product_to_edit = null;
        if (isset($_GET['edit_id'])) {
            $id = intval($_GET['edit_id']);
            if ($use_woocommerce) {
                 // Busca o produto no WC
                 $product_to_edit_wc = wc_get_product($id);
                 if ($product_to_edit_wc) {
                     // Adapta o objeto WC para a estrutura de exibição
                     $product_to_edit = (object) array(
                         'id' => $id,
                         'name' => $product_to_edit_wc->get_name(),
                         'sku' => $product_to_edit_wc->get_sku(),
                         'barcode' => $product_to_edit_wc->get_meta('_barcode_field') ?: '', // Meta customizada
                         'price' => $product_to_edit_wc->get_price(),
                         'discount' => $product_to_edit_wc->get_meta('_pdv_discount') ?: 0, // Meta customizada
                         'quantity' => $product_to_edit_wc->get_stock_quantity(),
                         'validity_date' => $product_to_edit_wc->get_meta('_validity_date') ?: '', // Meta customizada
                         'is_variable' => $product_to_edit_wc->is_type('variable') ? 1 : 0,
                         'variations' => $product_to_edit_wc->is_type('variable') ? mpdm_get_wc_variations_data($product_to_edit_wc) : '[]',
                         // NOVO: Campos Fiscais do WC
                         'ncm' => $product_to_edit_wc->get_meta('_ncm_field') ?: '',
                         'cest' => $product_to_edit_wc->get_meta('_cest_field') ?: '',
                         'cfop' => $product_to_edit_wc->get_meta('_cfop_field') ?: '5102',
                     );
                 }
            } else {
                 $product_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            }

        } elseif (isset($_GET['delete_id'])) {
            $id = intval($_GET['delete_id']);
            if ($use_woocommerce) {
                // Remove o post (produto) do WC
                wp_delete_post($id, true);
            } else {
                $wpdb->delete($table_name, ['id' => $id]);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Produto excluído com sucesso!</p></div>';
            echo '<script>window.location.href = window.location.href.split("?")[0] + "?page=meu-pdv&tab=products";</script>';
        }

        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        $products = [];
        $total_products = 0;

        if ($use_woocommerce) {
            // Lógica de Paginação e Consulta usando WC_Product_Query
            $query_args = array(
                'status' => 'publish',
                'limit' => $per_page,
                'offset' => ($current_page - 1) * $per_page,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'paginate' => true, 
            );

            if (!empty($search_query)) {
                $query_args['s'] = $search_query;
            }

            $product_query = new WC_Product_Query($query_args);
            $query_results = $product_query->get_products();
            
            // CORREÇÃO: Utiliza o objeto de resultados retornado pelo get_products()
            $total_products = $query_results->total; 
            $product_ids = $query_results->products;

            foreach ($product_ids as $product_id) {
                $product_wc = wc_get_product($product_id);
                if (!$product_wc) continue;

                $product_data = (object) array(
                    'id' => $product_id,
                    'name' => $product_wc->get_name(),
                    'sku' => $product_wc->get_sku(),
                    'barcode' => $product_wc->get_meta('_barcode_field') ?: '',
                    'price' => $product_wc->get_price(),
                    'discount' => $product_wc->get_meta('_pdv_discount') ?: 0,
                    'quantity' => $product_wc->get_stock_quantity(),
                    'validity_date' => $product_wc->get_meta('_validity_date') ?: '',
                    'is_variable' => $product_wc->is_type('variable') ? 1 : 0,
                    'variations' => $product_wc->is_type('variable') ? mpdm_get_wc_variations_data($product_wc) : '[]',
                );

                $products[] = $product_data;
            }
        } else {
            // Lógica de Fallback com $wpdb
            $where_clause = '';
            if (!empty($search_query)) {
                $where_clause = $wpdb->prepare("WHERE name LIKE '%%%s%%' OR sku LIKE '%%%s%%' OR barcode LIKE '%%%s%%'", $search_query, $search_query, $search_query);
            }
            $total_products = $wpdb->get_var("SELECT COUNT(*) FROM " . $table_name . " " . $where_clause);
            $products = $wpdb->get_results("SELECT * FROM " . $table_name . " " . $where_clause . " ORDER BY id DESC LIMIT " . ($current_page - 1) * $per_page . ", " . $per_page);
        }
        
        $total_pages = ceil($total_products / $per_page);
        ?>
        <h2>Cadastro de Produtos</h2>
        <div id="product-form-container">
            <h3><?php echo $product_to_edit ? 'Editar Produto' : 'Adicionar Novo Produto'; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="add_product">
                <?php if ($product_to_edit) : ?>
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($product_to_edit->id); ?>">
                <?php endif; ?>
                <label>Nome:</label><br><input type="text" name="name" value="<?php echo esc_attr($product_to_edit->name ?? ''); ?>" required><br>
                
                <?php if ($use_woocommerce && $product_to_edit && $product_to_edit->is_variable) : ?>
                    <div class="notice notice-info"><p>Este é um produto variável do WooCommerce. Edite as variações diretamente no <a href="<?php echo get_edit_post_link($product_to_edit->id); ?>" target="_blank">editor do WooCommerce</a>.</p></div>
                <?php else: ?>
                    <label>Tipo de Produto:</label><br>
                    <input type="radio" name="product_type" value="simple" <?php checked($product_to_edit->is_variable ?? 0, 0); ?> > Simples
                    <input type="radio" name="product_type" value="variable" <?php checked($product_to_edit->is_variable ?? 0, 1); ?> > Variável
                    <hr>
                    <div id="simple-product-fields" style="<?php echo ($product_to_edit && $product_to_edit->is_variable) ? 'display: none;' : 'display: block;'; ?>">
                        <label>SKU (Código Interno):</label><br>
                        <div class="input-group">
                            <input type="text" name="sku" value="<?php echo esc_attr($product_to_edit->sku ?? ''); ?>" required>
                            <button type="button" id="mpdm_generate_sku_btn" class="button">Gerar SKU Aleatório</button>
                        </div>
                        <label>Código de Barras:</label><br>
                        <input type="text" name="barcode" value="<?php echo esc_attr($product_to_edit->barcode ?? ''); ?>"><br>
                        
                        <?php if (!mpdm_is_woocommerce_active()) : // Bloco de campos fiscais APENAS se WC NÃO estiver ativo ?>

                            <label>NCM (Código Fiscal):</label><br>
                            <input type="text" name="ncm" value="<?php echo esc_attr($product_to_edit->ncm ?? ''); ?>" placeholder="Ex: 8471.30.12">
                            <p class="description">Classificação de Mercadoria. Essencial para NF-e.</p>

                            <label>CEST:</label><br>
                            <input type="text" name="cest" value="<?php echo esc_attr($product_to_edit->cest ?? ''); ?>" placeholder="Opcional. Ex: 21.053.00">
                            <p class="description">Código Especificador da Substituição Tributária (se aplicável).</p>

                            <label>CFOP Padrão:</label><br>
                            <input type="text" name="cfop" value="<?php echo esc_attr($product_to_edit->cfop ?? '5102'); ?>" required placeholder="Ex: 5102 (Venda dentro do estado)">
                            <p class="description">Código Fiscal de Operações e Prestações. Usado na NF-e.</p>
                            <?php else : ?>
                            <div class="notice notice-info"><p>Os dados fiscais (NCM/CEST/CFOP) são gerenciados na aba **Dados Fiscais (PDV)** no editor do WooCommerce.</p></div>
                        <?php endif; ?>

                        <label>Preço:</label><br><input type="number" step="0.01" name="price" value="<?php echo esc_attr($product_to_edit->price ?? ''); ?>" required><br>
                        <label>Desconto do Produto (%):</label><br><input type="number" name="discount" value="<?php echo esc_attr($product_to_edit->discount ?? 0); ?>" min="0" max="100"><br>
                        <label>Quantidade:</label><br><input type="number" name="quantity" value="<?php echo esc_attr($product_to_edit->quantity ?? ''); ?>" required><br><br>
                        <label>Data de Validade:</label><br><input type="date" name="validity_date" value="<?php echo esc_attr($product_to_edit->validity_date ?? ''); ?>"><br><br>
                    </div>
                    <div id="variable-product-fields" style="<?php echo ($product_to_edit && $product_to_edit->is_variable) ? 'display: block;' : 'display: none;'; ?>">
                        <label>Atributos de Variação (ex: cor, tamanho):</label><br>
                        <input type="text" id="variation-attribute" placeholder="Cor"><br>
                        <label>Valores (ex: azul, preto):</label><br>
                        <input type="text" id="variation-values" placeholder="azul, preto"><br>
                        <button type="button" id="add-variations-btn" class="button">Gerar Variações</button>
                        <div id="variation-fields-container">
                                                    <?php if ($product_to_edit && $product_to_edit->is_variable) :
                                                         $variations = json_decode($product_to_edit->variations, true);
                                                         foreach ($variations as $variation) :
                                                             $variation_name = esc_html($variation['name']);
                                                             $variation_sku = esc_attr($variation['sku']);
                                                             $variation_barcode = esc_attr($variation['barcode']);
                                                             $variation_price = esc_attr($variation['price']);
                                                             $variation_discount = esc_attr($variation['discount']);
                                                             $variation_quantity = esc_attr($variation['quantity']);
                                                             ?>
                                                             <h4>Variação: <?php echo $variation_name; ?></h4>
                                                             <label>SKU:</label><br>
                                                             <div class="input-group">
                                                                 <input type="text" name="variations[<?php echo $variation_sku; ?>][sku]" value="<?php echo $variation_sku; ?>" required>
                                                                 <button type="button" class="button mpdm_generate_sku_btn_variation">Gerar SKU Aleatório</button>
                                                             </div>
                                                             <label>Código de Barras:</label><br>
                                                             <input type="text" name="variations[<?php echo $variation_sku; ?>][barcode]" value="<?php echo $variation_barcode; ?>"><br>
                                                             <label>Preço:</label><br><input type="number" step="0.01" name="variations[<?php echo $variation_sku; ?>][price]" value="<?php echo $variation_price; ?>" required><br>
                                                             <label>Desconto da Variação (%):</label><br><input type="number" name="variations[<?php echo $variation_sku; ?>][discount]" value="<?php echo $variation_discount; ?>" min="0" max="100"><br>
                                                             <label>Quantidade:</label><br><input type="number" name="variations[<?php echo $variation_sku; ?>][quantity]" value="<?php echo $variation_quantity; ?>" required><br>
                                                             <input type="hidden" name="variations[<?php echo $variation_sku; ?>][name]" value="<?php echo $variation_name; ?>">
                                                             <hr>
                                                         <?php endforeach; ?>
                                                    <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="submit" class="button button-primary" value="<?php echo $product_to_edit ? 'Salvar Alterações' : 'Salvar Produto'; ?>">
            </form>
        </div>
        <hr>
        <h3>Produtos Cadastrados</h3>
        <div class="tablenav top">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="meu-pdv">
                <input type="hidden" name="tab" value="products">
                <p class="search-box">
                    <label class="screen-reader-text" for="product-search-input">Pesquisar produtos:</label>
                    <input type="search" id="product-search-input" name="s" value="<?php echo esc_attr($search_query); ?>">
                    <input type="submit" id="search-submit" class="button" value="Pesquisar">
                </p>
            </form>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $total_products; ?> itens</span>
                <?php
                $page_links = paginate_links(array(
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'add_args'  => ['s' => $search_query, 'tab' => 'products'],
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $current_page,
                ));
                if ($page_links) {
                    echo $page_links;
                }
                ?>
            </div>
            <div>
                </div>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr><th>Nome</th><th>SKU</th><th>Código de Barras</th><th>Preço</th><th>Desconto (%)</th><th>Quantidade</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($products)) : ?>
                    <?php foreach ($products as $product) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html($product->name); ?>
                                <?php if ($product->is_variable) : ?>
                                    <small>(Variável)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($product->sku); ?></td>
                            <td><?php echo esc_html($product->barcode); ?></td>
                            <td>R$ <?php echo esc_html(number_format($product->price, 2, ',', '.')); ?></td>
                            <td><?php echo esc_html($product->discount); ?>%</td>
                            <td><?php echo esc_html($product->quantity); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'meu-pdv', 'tab' => 'products', 'edit_id' => $product->id])); ?>" class="button">Editar</a>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'meu-pdv', 'tab' => 'products', 'delete_id' => $product->id])); ?>" class="button button-secondary" onclick="return confirm('Tem certeza que deseja excluir este produto?');">Excluir</a>
                            </td>
                        </tr>
                        <?php if ($product->is_variable && $product->variations) : ?>
                            <?php $variations = json_decode($product->variations, true); ?>
                            <?php if ($variations) : ?>
                                <?php foreach ($variations as $variation) : ?>
                                <tr class="variation-row">
                                    <td> - <?php echo esc_html($variation['name']); ?></td>
                                    <td><?php echo esc_html($variation['sku']); ?></td>
                                    <td><?php echo esc_html($variation['barcode']); ?></td>
                                    <td>R$ <?php echo esc_html(number_format($variation['price'], 2, ',', '.')); ?></td>
                                    <td><?php echo esc_html($variation['discount']); ?>%</td>
                                    <td><?php echo esc_html($variation['quantity']); ?></td>
                                    <td></td> </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">Nenhum produto encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const simpleFields = document.getElementById('simple-product-fields');
                const variableFields = document.getElementById('variable-product-fields');
                const productTypeRadios = document.getElementsByName('product_type');
                
                const skuInput = document.querySelector('#simple-product-fields input[name="sku"]');
                const priceInput = document.querySelector('#simple-product-fields input[name="price"]');
                const discountInput = document.querySelector('#simple-product-fields input[name="discount"]');
                const quantityInput = document.querySelector('#simple-product-fields input[name="quantity"]');

                productTypeRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.value === 'simple') {
                            simpleFields.style.display = 'block';
                            variableFields.style.display = 'none';
                            skuInput.setAttribute('required', 'required');
                            priceInput.setAttribute('required', 'required');
                            quantityInput.setAttribute('required', 'required');
                        } else {
                            simpleFields.style.display = 'none';
                            variableFields.style.display = 'block';
                            skuInput.removeAttribute('required');
                            priceInput.removeAttribute('required');
                            quantityInput.removeAttribute('required');
                        }
                    });
                });

                const addVariationsBtn = document.getElementById('add-variations-btn');
                const variationContainer = document.getElementById('variation-fields-container');

                addVariationsBtn.addEventListener('click', function() {
                    const attribute = document.getElementById('variation-attribute').value;
                    const values = document.getElementById('variation-values').value.split(',').map(v => v.trim());
                    const baseName = document.querySelector('input[name="name"]').value;
                    variationContainer.innerHTML = '';
                    values.forEach(value => {
                        const variationName = `${baseName} - ${value}`;
                        const variationHtml = `
                            <h4>Variação: ${variationName}</h4>
                            <label>SKU:</label><br>
                            <div class="input-group">
                                <input type="text" name="variations[${value}][sku]" required>
                                <button type="button" class="button mpdm_generate_sku_btn_variation">Gerar SKU Aleatório</button>
                            </div>
                            <label>Código de Barras:</label><br>
                            <input type="text" name="variations[${value}][barcode]"><br>
                            <label>Preço:</label><br><input type="number" step="0.01" name="variations[${value}][price]" required><br>
                            <label>Desconto da Variação (%):</label><br><input type="number" name="variations[${value}][discount]" value="0" min="0" max="100"><br>
                            <label>Quantidade:</label><br><input type="number" name="variations[${value}][quantity]" required><br>
                            <input type="hidden" name="variations[${value}][name]" value="${variationName}">
                            <hr>
                        `;
                        variationContainer.insertAdjacentHTML('beforeend', variationHtml);
                    });
                     document.querySelectorAll('.mpdm_generate_sku_btn_variation').forEach(btn => {
                             btn.addEventListener('click', function() {
                                 const targetInput = this.previousElementSibling;
                                 targetInput.value = generateRandomSku();
                             });
                         });
                });
                
                // Lógica para gerar SKU aleatório
                const generateSkuBtn = document.getElementById('mpdm_generate_sku_btn');
                if (generateSkuBtn) {
                    generateSkuBtn.addEventListener('click', function() {
                        const targetInput = this.previousElementSibling;
                        targetInput.value = generateRandomSku();
                    });
                }
                
                // Anexa a função de geração de SKU às variações existentes (após o carregamento da página de edição)
                document.querySelectorAll('.mpdm_generate_sku_btn_variation').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const targetInput = this.previousElementSibling;
                        targetInput.value = generateRandomSku();
                    });
                });

                function generateRandomSku() {
                    const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    let result = '';
                    for (let i = 0; i < 12; i++) {
                        result += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    return result;
                }
            });
        </script>
        <?php
    }
}

if (!function_exists('mpdm_render_stock_tab')) {
    function mpdm_render_stock_tab() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        ?>
        <h2>Entrada de Estoque</h2>
        <p>Use o campo abaixo para adicionar mais unidades a um produto existente. Insira o SKU do produto simples ou da variação.</p>
        <form method="post">
            <input type="hidden" name="action" value="add_stock">
            <label>SKU do Produto/Variação:</label><br><input type="text" name="sku" required><br>
            <label>Quantidade a Adicionar:</label><br><input type="number" name="quantity" required min="1"><br><br>
            <label>Nova Data de Validade:</label><br><input type="date" name="validity_date"><br><br>
            <input type="submit" class="button button-primary" value="Adicionar ao Estoque">
        </form>
        <?php
    }
}

if (!function_exists('mpdm_render_pos_tab')) {
    function mpdm_render_pos_tab() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $company_logo = get_option('mpdm_company_logo', '');
        ?>
        <div id="pos-container">
            <div class="pos-controls">
                <button id="zoom-out-btn" class="button">-</button>
                <button id="zoom-in-btn" class="button">+</button>
                <button id="fullscreen-toggle-btn" class="button"><span class="dashicons dashicons-editor-expand"></span></button>
            </div>
            <div id="pos-left" class="pos-panel">
                <div class="input-group">
                    <input type="text" id="pos-identifier-input" placeholder="Ler código de barras ou digitar SKU"> 
                    <input type="number" id="pos-quantity-multiplier" value="1" min="1" style="width: 60px;">
                </div>
                
                <ul id="pos-cart"></ul>
            </div>
            <div id="pos-right" class="pos-panel">
                <?php if (!empty($company_logo)) : ?>
                    <img src="<?php echo esc_url($company_logo); ?>" alt="Logo da Empresa" class="pos-company-logo">
                <?php endif; ?>
                <div id="receipt-summary-container">
                    <div class="input-group">
                        <input type="text" id="customer-cpf-input" placeholder="CPF do Cliente (Opcional)">
                        <span id="customer-info"></span>
                    </div>
                    <div class="total-display">
                        <span class="label">Total a Pagar</span>
                        <span id="total-value">R$ 0,00</span>
                    </div>
                    <div id="discount-info"></div>
                    <div class="payment-details-group">
                        <label for="payment-method">Forma de Pagamento:</label>
                        <select id="payment-method">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">Pix</option>
                        </select>
                        <div id="dinheiro-fields">
                            <label for="amount-paid">Valor Recebido:</label>
                            <input type="number" id="amount-paid" step="0.01">
                            <div id="change-display">Troco: <span id="change-value">R$ 0,00</span></div>
                        </div>
                        <div id="pix-fields" style="display:none;">
                            <p>Pague com Pix para a chave:</p>
                            <strong id="pix-key-display"><?php echo esc_html(get_option('mpdm_pix_key', 'Chave Pix não configurada')); ?></strong>
                        </div>
                    </div>
                    <div class="mpdm-form-buttons">
                        <button id="add-unregistered-item-btn" class="button button-primary">Adicionar Item Manualmente</button>
                        <button id="checkout-btn" class="button button-primary">Finalizar Venda</button>
                        <button id="clear-cart-btn" class="button" style="display:none;">Limpar Carrinho</button>
                    </div>
                </div>
                
                <div id="receipt-container">
                    <h3>Recibo da Venda</h3>
                    <div id="pos-receipt-content"></div>
                    <div id="receipt-buttons-container">
                        <button id="sale-done-btn" class="button button-primary">Nova Venda</button>
                        <button id="receipt-print-btn" class="button">Imprimir Recibo</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="pix-modal" class="mpdm-modal">
            <div class="mpdm-modal-content">
                <span class="close">&times;</span>
                <h2>Pagar com Pix</h2>
                <div id="pix-qr-code"></div>
                <div style="text-align: left; margin-top: 20px;">
                    <p><strong>Valor:</strong> <span id="pix-value"></span></p>
                    <p><strong>Chave Pix:</strong> <span id="pix-key"></span></p>
                </div>
                <div class="modal-buttons">
                    <button id="pix-modal-close-btn" class="button button-primary">Fechar</button>
                </div>
            </div>
        </div>
        
        <div id="unregistered-item-modal" class="mpdm-modal">
            <div class="mpdm-modal-content">
                <span class="close">&times;</span>
                <h2>Adicionar Item não Cadastrado</h2>
                <label for="unregistered-item-name">Nome do Item:</label>
                <input type="text" id="unregistered-item-name" name="unregistered-item-name" placeholder="Ex: Taxa de entrega" required>
                <label for="unregistered-item-price">Preço:</label>
                <input type="number" id="unregistered-item-price" name="unregistered-item-price" step="0.01" required>
                <label for="unregistered-item-quantity">Quantidade:</label>
                <input type="number" id="unregistered-item-quantity" name="unregistered-item-quantity" value="1" min="1" required>
                <div class="modal-buttons">
                    <button id="unregistered-item-add-btn" class="button button-primary">Adicionar</button>
                    <button id="unregistered-item-cancel-btn" class="button">Cancelar</button>
                </div>
            </div>
        </div>
        <script src="https://unpkg.com/qrcode-svg@1.1.0/lib/qrcode.min.js"></script>
        <script>
            jQuery(document).ready(function($) {
                console.log("PDV: Script carregado.");

                const identifierInput = $('#pos-identifier-input');
                const quantityMultiplierInput = $('#pos-quantity-multiplier');
                const customerCpfInput = $('#customer-cpf-input');
                const customerInfoSpan = $('#customer-info');
                const discountInfoDiv = $('#discount-info');
                const cartList = $('#pos-cart');
                const totalValueSpan = $('#total-value');
                const checkoutBtn = $('#checkout-btn');
                const clearCartBtn = $('#clear-cart-btn');
                const saleDoneBtn = $('#sale-done-btn');
                const receiptContainer = $('#receipt-container');
                const receiptContent = $('#pos-receipt-content');
                const receiptPrintBtn = $('#receipt-print-btn');
                const receiptSummaryContainer = $('#receipt-summary-container');
                
                const paymentMethod = $('#payment-method');
                const dinheiroFields = $('#dinheiro-fields');
                const pixFields = $('#pix-fields');
                const amountPaidInput = $('#amount-paid');
                const changeValueSpan = $('#change-value');
                const addUnregisteredItemBtn = $('#add-unregistered-item-btn');
                const unregisteredItemModal = $('#unregistered-item-modal');
                const unregisteredItemName = $('#unregistered-item-name');
                const unregisteredItemPrice = $('#unregistered-item-price');
                const unregisteredItemQuantity = $('#unregistered-item-quantity');
                const unregisteredItemAddBtn = $('#unregistered-item-add-btn');
                const unregisteredItemCancelBtn = $('#unregistered-item-cancel-btn');
                const modalClose = $('.mpdm-modal-content .close');
                const fullscreenBtn = $('#fullscreen-toggle-btn');
                const zoomInBtn = $('#zoom-in-btn');
                const zoomOutBtn = $('#zoom-out-btn');
                const posContainer = $('#pos-container');
                const body = $('body');
                const pixModal = $('#pix-modal');

                // VARIÁVEIS PARA A LÓGICA DE DEBOUNCING (1 SEGUNDO)
                let typingTimer;              
                const doneTypingInterval = 1000; // 1 segundo
                
                let cart = [];
                let total = 0;
                let currentCustomerCpf = null;
                let currentZoom = parseFloat(mpdm_ajax.zoom);

                posContainer.css('font-size', (currentZoom * 16) + 'px');

                function updateReceiptContent() {
                    const paymentMethodValue = paymentMethod.val();
                    const amountPaid = parseFloat(amountPaidInput.val().replace(',', '.')) || 0;
                    
                    const companyName = '<?php echo esc_js(get_option("mpdm_company_name", "")); ?>';
                    const companyAddress = '<?php echo esc_js(get_option("mpdm_company_address", "")); ?>';
                    const companyPhone = '<?php echo esc_js(get_option("mpdm_company_phone", "")); ?>';
                    const companyLogo = '<?php echo esc_js(get_option("mpdm_company_logo", "")); ?>';
                    const pixKey = '<?php echo esc_js(get_option("mpdm_pix_key", "")); ?>';

                    function chunkText(text, length) {
                        if (!text) return '';
                        const regex = new RegExp(`.{1,${length}}`, 'g');
                        const matches = text.match(regex);
                        return matches ? matches.join('\\n') : '';
                    }

                    let receiptHtml = '';
                    if (companyName) {
                        receiptHtml += `
    <h3>${companyName}</h3>
    <p>${companyAddress}</p>
    <p>Tel: ${companyPhone}</p>
    `;
                    }
                    receiptHtml += `
    <pre>
    ------------------------------
    Recibo de Venda
    Data: ${new Date().toLocaleString('pt-BR')}
    ------------------------------

    Itens:
    `;
                    let subtotal = 0;
                    let totalDiscountAmount = 0;
                    cart.forEach(item => {
                        const itemPrice = parseFloat(item.price);
                        const itemQuantity = parseInt(item.quantity);
                        const itemSubtotal = itemPrice * itemQuantity;
                        const itemDiscount = (currentCustomerCpf && (item.discount !== undefined && item.discount > 0)) ? parseFloat(item.discount) : 0;
                        const itemDiscountAmount = itemSubtotal * (itemDiscount / 100);
                        const itemFinalPrice = itemSubtotal - itemDiscountAmount;

                        const line = `${item.name.substring(0, 15).padEnd(16)} x${item.quantity.toString().padEnd(3)}R$ ${itemFinalPrice.toFixed(2).replace('.', ',')}`;
                        receiptHtml += line + `\n`;
                        subtotal += itemSubtotal;
                        totalDiscountAmount += itemDiscountAmount;
                    });
                    
                    const finalTotal = subtotal - totalDiscountAmount;

                    receiptHtml += `
    ------------------------------
    Subtotal: R$ ${subtotal.toFixed(2).replace('.', ',')}
    Desconto: R$ ${totalDiscountAmount.toFixed(2).replace('.', ',')}
    Total: R$ ${finalTotal.toFixed(2).replace('.', ',')}
    `;
                    if (paymentMethodValue === 'dinheiro') {
                        const change = amountPaid - finalTotal;
                        receiptHtml += `
    Valor Pago: R$ ${amountPaid.toFixed(2).replace('.', ',')}
    Troco:      R$ ${change.toFixed(2).replace('.', ',')}
    `;
                    } else if (paymentMethodValue === 'pix' && pixKey) {
                        receiptHtml += `
    Pagamento: PIX
    Chave: ${chunkText(pixKey, 20)}
    `;
                    }
                    receiptHtml += `
    ------------------------------
    </pre>
    `;
                    
                    receiptContent.html(receiptHtml);
                }

                function updateCartUI() {
                    cartList.empty();
                    let subtotal = 0;
                    let totalDiscountAmount = 0;
                    cart.forEach((item, index) => { // Adicionando index para exclusão
                        const itemPrice = parseFloat(item.price);
                        const itemQuantity = parseInt(item.quantity);
                        const itemSubtotal = itemPrice * itemQuantity;
                        const itemDiscount = (currentCustomerCpf && (item.discount !== undefined && item.discount > 0)) ? parseFloat(item.discount) : 0;
                        const itemDiscountAmount = itemSubtotal * (itemDiscount / 100);
                        const itemFinalPrice = itemSubtotal - itemDiscountAmount;
                        
                        // NOVO: Adiciona a lixeira
                        const deleteBtn = `<button class="delete-item-btn" data-index="${index}"><span class="dashicons dashicons-trash"></span></button>`;
                        
                        let discountText = itemDiscount > 0 ? `<br><small>Desconto: ${itemDiscount}%</small>` : '';
                        
                        const li = $('<li>');
                        li.html(`
                            <span>${item.name} (x${item.quantity}) ${discountText}</span>
                            <span style="display: flex; align-items: center;">
                                R$ ${itemFinalPrice.toFixed(2).replace('.', ',')}
                                ${deleteBtn}
                            </span>
                        `);
                        cartList.append(li);
                        
                        subtotal += itemSubtotal;
                        totalDiscountAmount += itemDiscountAmount;
                    });
                    
                    // NOVO: Handler para a lixeira
                    $('.delete-item-btn').on('click', function() {
                        const index = $(this).data('index');
                        cart.splice(index, 1); // Remove o item pelo índice
                        updateCartUI();
                        identifierInput.focus();
                    });

                    const finalTotal = subtotal - totalDiscountAmount;
                    total = finalTotal;
                    totalValueSpan.text('R$ ' + total.toFixed(2).replace('.', ','));
                    amountPaidInput.val(total.toFixed(2).replace('.', ',')).trigger('input');
                    discountInfoDiv.html(totalDiscountAmount > 0 ? `Desconto Total Aplicado: R$ ${totalDiscountAmount.toFixed(2).replace('.', ',')}` : '');
                    
                    if(cart.length > 0) {
                        clearCartBtn.show();
                        checkoutBtn.show();
                    } else {
                        clearCartBtn.hide();
                        checkoutBtn.hide();
                    }
                    updateReceiptContent();
                }
                
                // Função auxiliar para processar a adição (chamada pelo Debouncing ou ENTER)
                function processIdentifier() {
                    const identifier = identifierInput.val();
                    const quantity = parseInt(quantityMultiplierInput.val()) || 1;
                    if (identifier) {
                        fetchProductAndAddToCart(identifier, quantity);
                    } else {
                        // Se o Debounce disparar, mas o campo estiver vazio, não faz nada
                        // Isso é útil se o usuário limpa o campo rapidamente antes dos 1000ms
                    }
                }
                
                // NOVO: Keyup com Debouncing (1s)
                identifierInput.on('keyup', function(e) {
                    const $input = $(this);
                    
                    // Se for ENTER (key 13), cancela o temporizador e processa imediatamente
                    if (e.which == 13) {
                        clearTimeout(typingTimer);
                        console.log("PDV DEBUG: ENTER detectado. Disparando conferência.");
                        processIdentifier();
                        return;
                    }
                    
                    clearTimeout(typingTimer);
                    
                    if ($input.val()) { // Só inicia o temporizador se houver texto
                        typingTimer = setTimeout(function() {
                            console.log("PDV DEBUG: Pausa de 1s detectada. Disparando conferência.");
                            processIdentifier();
                        }, doneTypingInterval);
                    }
                });
                
                // Keydown: Limpa o temporizador (para ser mais preciso no reset)
                identifierInput.on('keydown', function() {
                    clearTimeout(typingTimer);
                });
                
                // NOVO: O botão de quantidade também deve disparar o processamento no ENTER (caso o usuário mude a qtd e aperte ENTER)
                quantityMultiplierInput.on('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        processIdentifier();
                    }
                });
                
                // REMOVIDO: O handler de keypress anterior foi substituído pelo Keyup com Debouncing.


                function updateFinalTotal() {
                    let subtotal = 0;
                    let totalDiscountAmount = 0;
                    cart.forEach(item => {
                        const itemPrice = parseFloat(item.price);
                        const itemQuantity = parseInt(item.quantity);
                        const itemSubtotal = itemPrice * itemQuantity;
                        const itemDiscount = (currentCustomerCpf && (item.discount !== undefined && item.discount > 0)) ? parseFloat(item.discount) : 0;
                        const itemDiscountAmount = itemSubtotal * (itemDiscount / 100);
                        subtotal += itemSubtotal;
                        totalDiscountAmount += itemDiscountAmount;
                    });
                    
                    const finalTotal = subtotal - totalDiscountAmount;
                    total = finalTotal;
                    totalValueSpan.text('R$ ' + total.toFixed(2).replace('.', ','));
                    amountPaidInput.val(total.toFixed(2).replace('.', ',')).trigger('input');
                    discountInfoDiv.html(totalDiscountAmount > 0 ? `Desconto Total Aplicado: R$ ${totalDiscountAmount.toFixed(2).replace('.', ',')}` : '');
                    updateReceiptContent();
                }

                paymentMethod.on('change', function() {
                    if (this.value === 'dinheiro') {
                        dinheiroFields.show();
                        pixFields.hide();
                    } else {
                        dinheiroFields.hide();
                        pixFields.show();
                    }
                    updateReceiptContent();
                });

                amountPaidInput.on('input', function() {
                    try {
                        const amountPaid = parseFloat($(this).val().replace(',', '.')) || 0;
                        const change = amountPaid - total;
                        const changeFormatted = change.toFixed(2).replace('.', ',');
                        
                        changeValueSpan.text('R$ ' + changeFormatted);
                        
                        if (change < 0) {
                            changeValueSpan.addClass('negative');
                        } else {
                            changeValueSpan.removeClass('negative');
                        }
                        
                        updateReceiptContent();
                    } catch (e) {
                        console.error("PDV: Erro ao calcular troco:", e);
                    }
                });
                
                function fetchProductAndAddToCart(identifier, quantity = 1) {
                    if (!identifier) {
                        alert('Por favor, insira um SKU ou código de barras.');
                        return;
                    }
                    const data = {
                        'action': 'mpdm_get_product_by_identifier',
                        'identifier': identifier,
                        'security': mpdm_ajax.nonce
                    };
                    $.post(mpdm_ajax.ajaxurl, data, function(response) {
                        if (response && response.name) {
                            const existingItem = cart.find(item => item.sku === response.sku);
                            if (existingItem) {
                                existingItem.quantity += quantity;
                            } else {
                                // Mapeia os dados fiscais retornados para o carrinho
                                const product = { 
                                    ...response, 
                                    price: parseFloat(response.price), 
                                    quantity: quantity, 
                                    discount: parseFloat(response.discount) || 0,
                                    ncm: response.ncm || '', // Inclui NCM
                                    cest: response.cest || '', // Inclui CEST
                                    cfop: response.cfop || '5102', // Inclui CFOP
                                };
                                cart.push(product);
                            }
                            updateCartUI();
                            identifierInput.val('');
                            quantityMultiplierInput.val('1');
                            identifierInput.focus();
                        } else {
                            alert('Produto não encontrado!');
                        }
                    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX Error: ' + textStatus, errorThrown);
                        alert('Erro ao buscar produto. Verifique a conexão.');
                    });
                }

                customerCpfInput.on('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const cpf = $(this).val();
                        if (cpf) {
                            const data = {
                                'action': 'mpdm_get_customer_by_cpf', 
                                'cpf': cpf,
                                'security': mpdm_ajax.nonce
                            };
                            $.post(mpdm_ajax.ajaxurl, data, function(response) {
                                if (response && response.name) {
                                    currentCustomerCpf = response.cpf;
                                    customerInfoSpan.html(`Cliente: <strong>${response.name}</strong>`);
                                    alert(`Cliente ${response.name} encontrado! Os descontos dos produtos serão aplicados.`);
                                    updateCartUI(); // Atualiza a UI para aplicar o desconto
                                } else {
                                    currentCustomerCpf = null;
                                    customerInfoSpan.html('');
                                    discountInfoDiv.html('');
                                    updateCartUI();
                                    alert('Cliente não encontrado. Nenhum desconto aplicado.');
                                }
                            }, 'json');
                        } else {
                            currentCustomerCpf = null;
                            customerInfoSpan.html('');
                            discountInfoDiv.html('');
                            updateCartUI();
                        }
                    }
                });
                
                function resetPos() {
                    cart = [];
                    currentCustomerCpf = null;
                    customerCpfInput.val('');
                    customerInfoSpan.html('');
                    discountInfoDiv.html('');
                    updateCartUI();
                    receiptContainer.hide();
                    receiptSummaryContainer.show();
                    identifierInput.focus();
                    amountPaidInput.val('');
                }
                clearCartBtn.on('click', resetPos);

                checkoutBtn.on('click', function() {
                    if (cart.length === 0) {
                        alert('Adicione itens ao carrinho.');
                        return;
                    }
                    
                    const paymentMethodValue = paymentMethod.val();
                    const amountPaid = parseFloat(amountPaidInput.val().replace(',', '.')) || 0;

                    if (paymentMethodValue === 'dinheiro' && amountPaid < total) {
                        alert('O valor pago é menor que o total.');
                        return;
                    }
                    
                    if (paymentMethodValue === 'pix') {
                        if (mpdm_ajax.pix_key === '') {
                            alert('A chave Pix não foi configurada. Por favor, adicione uma chave nas Configurações.');
                            return;
                        }

                        const totalFormatted = total.toFixed(2).replace('.', ',');
                        
                        // Estrutura PIX estática (simplificada)
                        const payload = `00020126330014BR.GOV.BCB.PIX0111${mpdm_ajax.pix_key}5204000053039865405${total.toFixed(2)}5802BR5913${mpdm_ajax.pix_name.toUpperCase()}6009${mpdm_ajax.pix_city.toUpperCase()}62070503***6304${(total.toFixed(2).replace('.','') + '000').slice(0, 4)}`

                        $('#pix-value').text('R$ ' + totalFormatted);
                        $('#pix-key').text(mpdm_ajax.pix_key);
                        $('#pix-qr-code').html(`<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(payload)}" alt="QR Code Pix">`);
                        
                        pixModal.show();
                    } else {
                        finalizeSaleAjax();
                    }
                });
                
                $('#pix-modal-close-btn').on('click', function() {
                    finalizeSaleAjax();
                    pixModal.hide();
                });
                
                $('#pix-modal .close').on('click', function() {
                    pixModal.hide();
                });


                function finalizeSaleAjax() {
                    const data = {
                        'action': 'mpdm_finalize_sale',
                        'cart': JSON.stringify(cart),
                        'total_amount': total,
                        'customer_cpf': currentCustomerCpf,
                        'payment_method': paymentMethod.val(),
                        'amount_paid': amountPaidInput.val(),
                        'security': mpdm_ajax.nonce
                    };
                    
                    console.log('Dados enviados para a finalização da venda:', data);
                    
                    $.post(mpdm_ajax.ajaxurl, data, function(response) {
                        if (response.success) {
                            alert('Venda finalizada com sucesso!');
                            receiptSummaryContainer.hide();
                            receiptContainer.show();
                        } else {
                            alert('Erro ao registrar a venda: ' + (response.data || 'Erro desconhecido. Verifique o log do servidor.'));
                            console.error('Erro no servidor ao registrar a venda:', response);
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('PDV: Erro na requisição AJAX para finalizar a venda:', textStatus, errorThrown, jqXHR);
                        alert('Erro ao finalizar venda. Verifique a conexão ou o console do navegador.');
                    });
                }
                
                saleDoneBtn.on('click', function() {
                    resetPos();
                });
                
                addUnregisteredItemBtn.on('click', function() {
                    unregisteredItemModal.show();
                });

                unregisteredItemCancelBtn.on('click', function() {
                    unregisteredItemModal.hide();
                    unregisteredItemName.val('');
                    unregisteredItemPrice.val('');
                    unregisteredItemQuantity.val('1');
                });

                modalClose.on('click', function() {
                    unregisteredItemModal.hide();
                    unregisteredItemName.val('');
                    unregisteredItemPrice.val('');
                    unregisteredItemQuantity.val('1');
                });
                
                unregisteredItemAddBtn.on('click', function() {
                    const itemName = unregisteredItemName.val();
                    const itemPrice = parseFloat(unregisteredItemPrice.val()) || 0;
                    const itemQuantity = parseInt(unregisteredItemQuantity.val()) || 1;
                    
                    if (itemName && itemPrice > 0 && itemQuantity > 0) {
                        const unregisteredProduct = {
                            name: itemName,
                            price: itemPrice,
                            quantity: itemQuantity,
                            sku: 'unregistered-' + new Date().getTime(),
                            discount: 0,
                            is_unregistered: true
                        };
                        const existingItem = cart.find(item => item.sku === unregisteredProduct.sku);
                        if (existingItem) {
                            existingItem.quantity += unregisteredProduct.quantity;
                        } else {
                            cart.push(unregisteredProduct);
                        }
                        updateCartUI();
                        unregisteredItemModal.hide();
                        unregisteredItemName.val('');
                        unregisteredItemPrice.val('');
                        unregisteredItemQuantity.val('1');
                    } else {
                        alert('Por favor, preencha todos os campos corretamente.');
                    }
                });

                clearCartBtn.hide();
                checkoutBtn.hide();
                receiptContainer.hide();
                unregisteredItemModal.hide();
                
                $(document).on('click', '#receipt-print-btn', function() {
                    const receiptHtml = $('#pos-receipt-content').html();
                    const printWindow = window.open('', '', 'height=600,width=800');
                    printWindow.document.write('<html><head><title>Recibo</title>');
                    printWindow.document.write('</head><body style="font-family: monospace; font-size: 12px; margin: 0; padding: 10px;">');
                    printWindow.document.write('<div id="pos-receipt-print-content">' + receiptHtml + '</div>');
                    printWindow.document.close();
                    printWindow.print();
                });
                
                // Lógica dos botões de zoom
                function saveZoomLevel(level) {
                    $.post(mpdm_ajax.ajaxurl, {
                        action: 'mpdm_save_zoom',
                        zoom_level: level,
                        security: mpdm_ajax.nonce
                    });
                }

                zoomInBtn.on('click', function() {
                    currentZoom = Math.min(2.0, currentZoom + 0.1);
                    posContainer.css('font-size', (currentZoom * 16) + 'px');
                    saveZoomLevel(currentZoom);
                });

                zoomOutBtn.on('click', function() {
                    currentZoom = Math.max(0.8, currentZoom - 0.1);
                    posContainer.css('font-size', (currentZoom * 16) + 'px');
                    saveZoomLevel(currentZoom);
                });
                
                // Lógica de "tela cheia" personalizada
                function toggleFullscreenClass() {
                    const body = $('body');
                    const isFullscreen = body.hasClass('mpdm-fullscreen');
                    if (isFullscreen) {
                        body.removeClass('mpdm-fullscreen');
                        localStorage.setItem('mpdm_fullscreen_mode', 'false');
                    } else {
                        body.addClass('mpdm-fullscreen');
                        localStorage.setItem('mpdm_fullscreen_mode', 'true');
                    }
                }

                if (localStorage.getItem('mpdm_fullscreen_mode') === 'true') {
                    $('body').addClass('mpdm-fullscreen');
                }

                fullscreenBtn.on('click', function() {
                    toggleFullscreenClass();
                });

                // Inicializa o total final na primeira carga
                updateFinalTotal();
            });
        </script>
        <?php
    }
}

if (!function_exists('mpdm_render_history_tab')) {
    function mpdm_render_history_tab() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mpdm_sales';

        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where_clause = '';
        if (!empty($search_query)) {
            $where_clause = $wpdb->prepare("WHERE sale_date LIKE '%%%s%%' OR id LIKE '%%%s%%' OR items LIKE '%%%s%%'", $search_query, $search_query, $search_query);
        }

        $sales = $wpdb->get_results("SELECT * FROM " . $table_name . " " . $where_clause . " ORDER BY sale_date DESC");
        ?>
        <h2>Histórico de Vendas</h2>
        <div class="tablenav top">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="meu-pdv">
                <input type="hidden" name="tab" value="history">
                <p class="search-box">
                    <label class="screen-reader-text" for="sale-search-input">Pesquisar vendas:</label>
                    <input type="search" id="sale-search-input" name="s" value="<?php echo esc_attr($search_query); ?>">
                    <input type="submit" id="search-submit" class="button" value="Pesquisar">
                </p>
            </form>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo count($sales); ?> itens</span>
                <?php
                // Lógica de paginação pode ser adicionada aqui se necessário
                ?>
            </div>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr><th>ID da Venda</th><th>Data</th><th>Total</th><th>Pagamento</th><th>Cliente (CPF)</th><th>Detalhes</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($sales)) : ?>
                    <?php foreach ($sales as $sale) : ?>
                        <tr>
                            <td><?php echo esc_html($sale->id); ?></td>
                            <td><?php echo esc_html($sale->sale_date); ?></td>
                            <td>R$ <?php echo esc_html(isset($sale->total_amount) ? number_format($sale->total_amount, 2, ',', '.') : 'N/A'); ?></td>
                            <td>
                                <?php echo esc_html(isset($sale->payment_method) ? $sale->payment_method : 'N/A'); ?>
                            </td>
                            <td><?php echo esc_html(isset($sale->customer_cpf) ? $sale->customer_cpf : 'N/A'); ?></td>
                            <td>
                                <div class="item-details">
                                    <?php
                                    $items = isset($sale->items) ? json_decode($sale->items, true) : null;
                                    if (!empty($items) && is_array($items)) {
                                        foreach ($items as $item) {
                                            $item_subtotal = floatval($item['price']) * intval($item['quantity']);
                                            $item_discount_amount = isset($item['discount']) ? $item_subtotal * (floatval($item['discount']) / 100) : 0;
                                            $item_final_price = $item_subtotal - $item_discount_amount;
                                            $discount_display = ($item_discount_amount > 0) ? " (-R$ " . number_format($item_discount_amount, 2, ',', '.') . ")" : "";
                                            echo '<div class="detail-item"><strong>' . esc_html($item['name']) . '</strong> (x' . esc_html($item['quantity']) . ') - R$ ' . esc_html(number_format($item_final_price, 2, ',', '.')) . ' ' . $discount_display . '</div>';
                                        }
                                    } else {
                                        echo 'Nenhum item encontrado.';
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">Nenhuma venda encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}

if (!function_exists('mpdm_render_reports_page')) {
    function mpdm_render_reports_page() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'mpdm_products';
        $sales_table = $wpdb->prefix . 'mpdm_sales';

        // Determinar o período do relatório a partir da URL
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'monthly';

        // Funções de coleta de dados de vendas por período
        function get_sales_data_by_period($period) {
            global $wpdb;
            $sales_table = $wpdb->prefix . 'mpdm_sales';
            $sql_format = '';
            $group_by = '';
            $order_by = '';

            switch ($period) {
                case 'daily':
                    $sql_format = '%Y-%m-%d';
                    $group_by = 'DATE(sale_date)';
                    $order_by = 'DATE(sale_date) DESC';
                    break;
                case 'weekly':
                    $sql_format = '%Y-%u';
                    $group_by = 'WEEK(sale_date, 1)';
                    $order_by = 'WEEK(sale_date, 1) DESC';
                    break;
                case 'monthly':
                default:
                    $sql_format = '%Y-%m';
                    $group_by = 'MONTH(sale_date)';
                    $order_by = 'MONTH(sale_date) DESC';
                    break;
            }

            return $wpdb->get_results("
                SELECT
                    DATE_FORMAT(sale_date, '{$sql_format}') AS period,
                    SUM(total_amount) AS total
                FROM {$sales_table}
                GROUP BY {$group_by}
                ORDER BY {$order_by}
            ", ARRAY_A);
        }

        // Processamento de dados de vendas para os top produtos
        $all_sales = $wpdb->get_results("SELECT items FROM " . $sales_table);
        $product_sales_count = [];

        foreach ($all_sales as $sale) {
            $items = json_decode($sale->items, true);
            if ($items) {
                foreach ($items as $item) {
                    if (isset($item['is_unregistered']) && $item['is_unregistered'] === true) {
                        continue;
                    }
                    $name = isset($item['name']) ? $item['name'] : 'Item Desconhecido';
                    $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                    $product_sales_count[$name] = isset($product_sales_count[$name]) ? $product_sales_count[$name] + $quantity : $quantity;
                }
            }
        }

        arsort($product_sales_count);
        $top_products = array_slice($product_sales_count, 0, 5, true);
        
        // Relatório de Estoque Baixo (Considerando WooCommerce se ativo)
        $low_stock_products = [];
        if (mpdm_is_woocommerce_active()) {
            $low_stock_args = array(
                'limit'     => 5,
                'status'    => 'publish',
                'orderby'   => 'meta_value_num',
                'order'     => 'ASC',
                'meta_key' => '_stock',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'       => '_stock',
                        'value'     => 10,
                        'compare' => '<',
                        'type'      => 'NUMERIC',
                    ),
                    array(
                        'key'       => '_manage_stock',
                        'value'     => 'yes',
                        'compare' => '=',
                    ),
                    array(
                        'key'       => '_virtual',
                        'value'     => 'yes',
                        'compare' => '!=',
                    ),
                ),
                'fields' => 'ids',
            );
            $low_stock_product_ids = (new WC_Product_Query($low_stock_args))->get_products();
            foreach ($low_stock_product_ids as $id) {
                $product = wc_get_product($id);
                if ($product) {
                    $low_stock_products[] = (object) ['name' => $product->get_name(), 'sku' => $product->get_sku(), 'quantity' => $product->get_stock_quantity()];
                }
            }
        } else {
             $low_stock_products = $wpdb->get_results("SELECT name, sku, quantity FROM " . $products_table . " WHERE quantity < 10 AND is_variable = 0 ORDER BY quantity ASC LIMIT 5");
        }

        // Relatório de Produtos Perto de Vencer
        $expiration_days = intval(get_option('mpdm_expiration_days', 7));
        $expiration_date_limit = date('Y-m-d', strtotime("+" . $expiration_days . " days"));
        
        $expiring_products = [];
        if (mpdm_is_woocommerce_active()) {
             $expiring_args = array(
                 'limit'     => -1,
                 'status'    => 'publish',
                 'meta_key' => '_validity_date',
                 'meta_value' => $expiration_date_limit,
                 'meta_compare' => '<=',
                 'orderby'   => 'meta_value',
                 'order'     => 'ASC',
                 'type'      => 'date',
                 'fields'    => 'ids',
               );
             $expiring_product_ids = (new WC_Product_Query($expiring_args))->get_products();
             foreach ($expiring_product_ids as $id) {
                 $product = wc_get_product($id);
                 if ($product) {
                     $expiring_products[] = (object) ['name' => $product->get_name(), 'sku' => $product->get_sku(), 'validity_date' => $product->get_meta('_validity_date')];
                 }
             }
        } else {
            $expiring_products_sql = $wpdb->prepare("SELECT name, sku, validity_date FROM " . $products_table . " WHERE validity_date <= %s AND validity_date IS NOT NULL ORDER BY validity_date ASC", $expiration_date_limit);
            $expiring_products = $wpdb->get_results($expiring_products_sql);
        }
        
        // Resumo Financeiro
        $total_sales_value = $wpdb->get_var("SELECT SUM(total_amount) FROM " . $sales_table);
        $total_sales_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $sales_table);

        // Define o título do período
        $period_title = '';
        switch ($period) {
            case 'daily':
                $period_title = 'Relatório Diário de Vendas';
                break;
            case 'weekly':
                $period_title = 'Relatório Semanal de Vendas';
                break;
            default:
                $period_title = 'Relatório Mensal de Vendas';
                break;
        }

        // Dados para o gráfico de vendas por período
        $sales_by_period = get_sales_data_by_period($period);

        ?>
        <div class="wrap mpdm-content">
            <h1>Relatórios e Análises</h1>
            <div style="text-align: right; margin-bottom: 20px;">
                <button id="export-all-reports-btn" class="button button-primary">Exportar Todos os Relatórios</button>
            </div>
            <div class="mpdm-reports-grid">
                
                <div class="mpdm-report-card yellow">
                    <h3>Resumo Financeiro</h3>
                    <?php
                    $total_sales_value = $total_sales_value !== null ? $total_sales_value : 0;
                    $total_sales_count = $total_sales_count !== null ? $total_sales_count : 0;
                    ?>
                    <p><strong>Total de Vendas:</strong> R$ <?php echo number_format($total_sales_value, 2, ',', '.'); ?></p>
                    <p><strong>Total de Transações:</strong> <?php echo intval($total_sales_count); ?></p>
                </div>

                <div class="mpdm-report-card green">
                    <h3>Top 5 Produtos Mais Vendidos</h3>
                    <div style="height: 200px; width: 200px; margin: 0 auto;">
                        <canvas id="top-products-chart"></canvas>
                    </div>
                </div>
                
                <div class="mpdm-report-card red">
                    <h3>Produtos com Estoque Baixo</h3>
                    <ul style="list-style-type: none; padding: 0;">
                        <?php if (!empty($low_stock_products)) : ?>
                            <?php foreach ($low_stock_products as $product) : ?>
                                <li style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 5px; border-bottom: 1px dashed #f0f0f0;">
                                    <span><?php echo esc_html($product->name); ?> (SKU: <?php echo esc_html($product->sku); ?>)</span>
                                    <span style="font-weight: bold;"><?php echo esc_html($product->quantity); ?> und.</span>
                                </li>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <li>Nenhum produto com estoque baixo.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="mpdm-report-card blue">
                    <h3>Produtos Perto de Vencer</h3>
                    <ul style="list-style-type: none; padding: 0;">
                        <?php if (!empty($expiring_products)) : ?>
                            <?php foreach ($expiring_products as $product) : ?>
                                <li style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 5px; border-bottom: 1px dashed #f0f0f0;">
                                    <span><?php echo esc_html($product->name); ?> (SKU: <?php echo esc_html($product->sku); ?>)</span>
                                    <span style="font-weight: bold; color: #e74c3c;"><?php echo date_i18n('d/m/Y', strtotime($product->validity_date)); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <li>Nenhum produto perto de vencer nos próximos <?php echo intval($expiration_days); ?> dias.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="mpdm-report-card" style="grid-column: 1 / -1;">
                    <h3><?php echo esc_html($period_title); ?></h3>
                    <div style="margin-bottom: 15px;">
                        <a href="<?php echo esc_url(add_query_arg('period', 'daily')); ?>" class="button <?php echo $period === 'daily' ? 'button-primary' : ''; ?>">Diário</a>
                        <a href="<?php echo esc_url(add_query_arg('period', 'weekly')); ?>" class="button <?php echo $period === 'weekly' ? 'button-primary' : ''; ?>">Semanal</a>
                        <a href="<?php echo esc_url(add_query_arg('period', 'monthly')); ?>" class="button <?php echo $period === 'monthly' ? 'button-primary' : ''; ?>">Mensal</a>
                    </div>
                    <?php if (!empty($sales_by_period)) : ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th>Total Vendido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_by_period as $row) : ?>
                            <tr>
                                <td>
                                    <?php
                                    $display_period = '';
                                    switch ($period) {
                                        case 'daily':
                                            $display_period = date_i18n('d/m/Y', strtotime($row['period']));
                                            break;
                                        case 'weekly':
                                            $week_number = substr($row['period'], 5);
                                            $year = substr($row['period'], 0, 4);
                                            $display_period = "Semana " . $week_number . " de " . $year;
                                            break;
                                        case 'monthly':
                                        default:
                                            $display_period = date_i18n('F Y', strtotime($row['period'] . '-01'));
                                            break;
                                    }
                                    echo esc_html($display_period);
                                    ?>
                                </td>
                                <td>
                                    <strong>R$ <?php echo number_format($row['total'], 2, ',', '.'); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                        <p>Nenhum dado de vendas para o período selecionado.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            let topProductsChart = null;

            const topProductsData = <?php echo json_encode($top_products); ?>;

            const backgroundColors = [
                '#4CAF50', '#2196F3', '#FFC107', '#E91E63', '#9C27B0'
            ];

            if (Object.keys(topProductsData).length > 0) {
                const ctxTopProducts = document.getElementById('top-products-chart');
                if (topProductsChart) {
                    topProductsChart.destroy();
                }
                topProductsChart = new Chart(ctxTopProducts, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(topProductsData),
                        datasets: [{
                            label: 'Quantidade Vendida',
                            data: Object.values(topProductsData),
                            backgroundColor: backgroundColors.slice(0, Object.keys(topProductsData).length)
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: { size: 10 }
                                }
                            }
                        }
                    }
                });
            } else {
                $('#top-products-chart').parent().html('<p>Nenhum dado de vendas para exibir.</p>');
            }

            // Lógica para o novo botão de exportação
            $('#export-all-reports-btn').on('click', function() {
                const originalText = $(this).text();
                $(this).prop('disabled', true).text('Gerando ZIP...');
                window.location.href = mpdm_ajax.ajaxurl + '?action=mpdm_export_all_reports';
                // Resetar o botão após um tempo, já que a requisição de download não retorna uma resposta AJAX
                setTimeout(() => {
                    $(this).prop('disabled', false).text(originalText);
                }, 5000); 
            });
        });
        </script>
        <?php
    }
}

/* NOVO: Página de Gerenciamento Fiscal */
if (!function_exists('mpdm_render_fiscal_page')) {
    function mpdm_render_fiscal_page() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        global $wpdb;
        $fiscal_table = $wpdb->prefix . 'mpdm_fiscal';

        // Lógica de filtro/busca (opcional)
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pendente';
        $where_clause = $wpdb->prepare("WHERE nfe_status = %s", $filter_status);

        $fiscal_records = $wpdb->get_results("SELECT * FROM " . $fiscal_table . " " . $where_clause . " ORDER BY sale_date DESC");

        ?>
        <div class="wrap mpdm-content mpdm-fiscal-container">
            <h1>Gestão Fiscal (Pré-faturamento NF-e)</h1>
            <p>Esta área armazena vendas prontas para serem faturadas como Notas Fiscais Eletrônicas.</p>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('status', 'pendente')); ?>" class="nav-tab <?php echo $filter_status == 'pendente' ? 'nav-tab-active' : ''; ?>">Pendentes (<?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$fiscal_table} WHERE nfe_status = 'pendente'"); ?>)</a>
                <a href="<?php echo esc_url(add_query_arg('status', 'concluida')); ?>" class="nav-tab <?php echo $filter_status == 'concluida' ? 'nav-tab-active' : ''; ?>">Concluídas (<?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$fiscal_table} WHERE nfe_status = 'concluida'"); ?>)</a>
                <a href="<?php echo esc_url(add_query_arg('status', 'erro')); ?>" class="nav-tab <?php echo $filter_status == 'erro' ? 'nav-tab-active' : ''; ?>">Com Erro (<?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$fiscal_table} WHERE nfe_status = 'erro'"); ?>)</a>
            </h2>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID Venda PDV</th>
                        <th>Data/Hora</th>
                        <th>Total</th>
                        <th>Cliente (CPF)</th>
                        <th>Status NF-e</th>
                        <th>Ações (NFE.io)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($fiscal_records)) : ?>
                        <?php foreach ($fiscal_records as $record) : ?>
                            <tr>
                                <td>#<?php echo esc_html($record->sale_id); ?></td>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($record->sale_date))); ?></td>
                                <td>R$ <?php echo esc_html(number_format($record->total_amount, 2, ',', '.')); ?></td>
                                <td><?php echo esc_html($record->customer_cpf ?: 'Consumidor Final'); ?></td>
                                <td><strong><?php echo esc_html(ucfirst($record->nfe_status)); ?></strong></td>
                                <td>
                                    <?php if ($record->nfe_status === 'pendente' || $record->nfe_status === 'erro') : ?>
                                        <button class="button button-primary nfe-send-btn" data-id="<?php echo $record->id; ?>">Enviar para NF-e</button>
                                    <?php endif; ?>
                                    <button class="button button-secondary nfe-details-btn" data-id="<?php echo $record->id; ?>">Ver Dados Fiscais</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6">Nenhum registro fiscal encontrado com status "<?php echo esc_html($filter_status); ?>".</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.nfe-send-btn').on('click', function() {
                alert('A integração com o NFE.io deve ser instalada separadamente. Este botão é um placeholder que dispararia a API do NFE.io para o registro de ID ' + $(this).data('id') + '!');
                // Implementação real da integração NFE.io viria aqui.
            });

            $('.nfe-details-btn').on('click', function() {
                // Em uma aplicação real, você faria uma chamada AJAX para buscar e exibir o JSON.
                alert('Os dados fiscais do item ' + $(this).data('id') + ' (JSON) estão prontos para a integração com o NFE.io. Você pode buscar esses dados na tabela mpdm_fiscal usando o sale_id.');
            });
        });
        </script>
        <?php
    }
}


if (!function_exists('mpdm_handle_form_submissions')) {
    function mpdm_handle_form_submissions() {
        if (!isset($_POST['action']) || !current_user_can('mpdm_manage_pos')) {
            return;
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'mpdm_products';

        switch ($_POST['action']) {
            case 'add_product':
                $use_woocommerce = mpdm_is_woocommerce_active();
                $name = sanitize_text_field($_POST['name']);
                $product_type = sanitize_text_field($_POST['product_type']);
                $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
                
                // NOVO: Coleta campos fiscais do POST (usado apenas se WC NÃO estiver ativo ou para campos WC)
                $ncm = sanitize_text_field($_POST['ncm'] ?? '');
                $cest = sanitize_text_field($_POST['cest'] ?? '');
                $cfop = sanitize_text_field($_POST['cfop'] ?? '5102');

                if ($use_woocommerce) {
                    // Lógica de manipulação do WooCommerce
                    
                    if ($product_type === 'simple' && $product_id === 0) {
                        // Novo produto simples
                        $product_wc = new WC_Product_Simple();
                        $product_wc->set_name($name);
                        $product_wc->set_sku(sanitize_text_field($_POST['sku']));
                        $product_wc->set_regular_price(floatval($_POST['price']));
                        $product_wc->set_stock_quantity(intval($_POST['quantity']));
                        $product_wc->set_manage_stock(true);
                        
                        $product_wc->update_meta_data('_barcode_field', sanitize_text_field($_POST['barcode']));
                        $product_wc->update_meta_data('_pdv_discount', intval($_POST['discount']));
                        $product_wc->update_meta_data('_validity_date', sanitize_text_field($_POST['validity_date']) ?: '');
                        
                        // NOVO: Salva metadados fiscais no WC
                        // Atenção: A partir de agora, os valores NCM/CEST/CFOP para WC vêm de $_POST['_ncm_field'] etc. (se fossem salvos pela aba customizada). 
                        // No seu caso atual, eles só virão do post se o WC estiver inativo. Mantemos a lógica para WC inativo e a lógica de meta fields do WC no novo hook.
                        $product_wc->update_meta_data('_ncm_field', $ncm);
                        $product_wc->update_meta_data('_cest_field', $cest);
                        $product_wc->update_meta_data('_cfop_field', $cfop);
                        
                        $product_wc->save();
                    } elseif ($product_type === 'simple' && $product_id > 0) {
                        // Atualização de produto simples existente
                        $product_wc = wc_get_product($product_id);
                        if ($product_wc && $product_wc->is_type('simple')) {
                            $product_wc->set_name($name);
                            $product_wc->set_sku(sanitize_text_field($_POST['sku']));
                            $product_wc->set_regular_price(floatval($_POST['price']));
                            $product_wc->set_stock_quantity(intval($_POST['quantity']));
                            
                            $product_wc->update_meta_data('_barcode_field', sanitize_text_field($_POST['barcode']));
                            $product_wc->update_meta_data('_pdv_discount', intval($_POST['discount']));
                            $product_wc->update_meta_data('_validity_date', sanitize_text_field($_POST['validity_date']) ?: '');

                            // NOVO: Atualiza metadados fiscais no WC
                            $product_wc->update_meta_data('_ncm_field', $ncm);
                            $product_wc->update_meta_data('_cest_field', $cest);
                            $product_wc->update_meta_data('_cfop_field', $cfop);
                            
                            $product_wc->save();
                        }
                    } else {
                        // Produto variável: forçamos a edição no WC
                        echo '<div class="notice notice-error is-dismissible"><p>A criação e edição de produtos variáveis deve ser feita diretamente no WooCommerce.</p></div>';
                        return;
                    }
                    
                } else {
                    // Lógica de Fallback com $wpdb
                    if ($product_type === 'simple') {
                        $sku = sanitize_text_field($_POST['sku']);
                        $barcode = sanitize_text_field($_POST['barcode']);
                        $price = floatval($_POST['price']);
                        $discount = intval($_POST['discount']);
                        $quantity = intval($_POST['quantity']);
                        $validity_date = sanitize_text_field($_POST['validity_date']) ?: null;
                        $is_variable = 0;
                        
                        // NOVO: Array com as variáveis fiscais para inserção/atualização
                        $product_data_insert_update = compact('name', 'sku', 'barcode', 'price', 'discount', 'quantity', 'validity_date', 'is_variable', 'ncm', 'cest', 'cfop');
                        
                        // Lógica de atualização ou inserção do $wpdb
                        if ($product_id > 0) {
                            $wpdb->update(
                                $products_table,
                                $product_data_insert_update,
                                ['id' => $product_id]
                            );
                        } else {
                            $wpdb->insert(
                                $products_table,
                                $product_data_insert_update
                            );
                        }
                    } else if ($product_type === 'variable') {
                        // Lógica de produto variável $wpdb (mantida)
                        $variations_data = [];
                        $total_quantity = 0;
                        if (isset($_POST['variations'])) {
                             foreach ($_POST['variations'] as $key => $variation) {
                                 $variations_data[] = [
                                     'name'     => sanitize_text_field($variation['name']),
                                     'sku'      => sanitize_text_field($variation['sku']),
                                     'barcode'  => sanitize_text_field($variation['barcode']),
                                     'price'    => floatval($variation['price']),
                                     'discount' => intval($variation['discount']),
                                     'quantity' => intval($variation['quantity']),
                                     // Campos fiscais são salvos no PARENT no DB customizado
                                   ];
                                 $total_quantity += intval($variation['quantity']); 
                             }
                        }
                        
                        if ($product_id > 0) {
                            $wpdb->update(
                                $products_table,
                                [
                                    'name'        => $name,
                                    'variations'  => json_encode($variations_data),
                                    'quantity'    => $total_quantity,
                                    'is_variable' => 1,
                                    'ncm'         => $ncm, // NOVO: Salva no Parent para o DB customizado
                                    'cest'        => $cest, // NOVO
                                    'cfop'        => $cfop, // NOVO
                                ],
                                ['id' => $product_id]
                            );
                        } else {
                            $wpdb->insert($products_table, [
                                'name'          => $name,
                                'sku'           => '',
                                'barcode'       => '',
                                'price'         => 0,
                                'discount'      => 0,
                                'quantity'      => $total_quantity,
                                'validity_date' => null,
                                'variations'    => json_encode($variations_data),
                                'is_variable'   => 1,
                                'ncm'           => $ncm, // NOVO
                                'cest'          => $cest, // NOVO
                                'cfop'          => $cfop, // NOVO
                            ]);
                        }
                    }
                }
                
                // Redireciona
                header('Location: ' . remove_query_arg(['edit_id', 'delete_id', 's', 'paged'], admin_url('admin.php?page=meu-pdv&tab=products')));
                exit;

            case 'add_stock':
                $use_woocommerce = mpdm_is_woocommerce_active();
                $sku = sanitize_text_field($_POST['sku']);
                $quantity_in = intval($_POST['quantity']);
                $validity_date = sanitize_text_field($_POST['validity_date']) ?: null;
                
                if ($use_woocommerce) {
                    // Lógica de ajuste de estoque do WooCommerce
                    $product_id_or_var_id = wc_get_product_id_by_sku($sku);
                    $product_wc = wc_get_product($product_id_or_var_id);
                    
                    if ($product_wc) {
                            // Garante que o gerenciamento de estoque está ativo no WC
                            if ($product_wc->get_manage_stock() === false) {
                                $product_wc->set_manage_stock(true);
                            }
                            $new_quantity = $product_wc->get_stock_quantity() + $quantity_in;
                            $product_wc->set_stock_quantity($new_quantity);
                            $product_wc->save();
                            $product_wc->update_meta_data('_validity_date', $validity_date); // Atualiza meta customizada
                            $product_wc->save_meta_data();
                            echo '<div class="notice notice-success is-dismissible"><p>Estoque do WooCommerce atualizado com sucesso!</p></div>';
                    } else {
                            echo '<div class="notice notice-error is-dismissible"><p>Erro: Produto (SKU: ' . esc_html($sku) . ') não encontrado no WooCommerce.</p></div>';
                    }
                } else {
                    // Lógica de Fallback com $wpdb (mantida)
                    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $products_table . " WHERE sku = %s", $sku));
                    
                    if ($product) {
                        if ($product->is_variable) {
                            $parent_product_id = $product->id;
                            $found_variation = false;
                            $variations = json_decode($product->variations, true);
                            foreach ($variations as &$variation) {
                                if ($variation['sku'] === $sku) {
                                    $variation['quantity'] += $quantity_in;
                                    $found_variation = true;
                                    break;
                                }
                            }
                            
                            if ($found_variation) {
                                $total_quantity_updated = array_sum(array_column($variations, 'quantity'));
                                $wpdb->update($products_table, 
                                    ['variations' => json_encode($variations), 'quantity' => $total_quantity_updated], 
                                    ['id' => $parent_product_id]);
                            } else {
                                $product_by_sku = $wpdb->get_row($wpdb->prepare("SELECT id, quantity, is_variable FROM " . $products_table . " WHERE sku = %s", $sku));
                                if ($product_by_sku && !$product_by_sku->is_variable) {
                                    $new_quantity = $product_by_sku->quantity + $quantity_in;
                                    $wpdb->update($products_table, ['quantity' => $new_quantity, 'validity_date' => $validity_date], ['sku' => $sku]);
                                }
                            }
                        } else {
                            $new_quantity = $product->quantity + $quantity_in;
                            $wpdb->update($products_table, ['quantity' => $new_quantity, 'validity_date' => $validity_date], ['sku' => $sku]);
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>Estoque customizado atualizado com sucesso!</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>Erro: Produto (SKU: ' . esc_html($sku) . ') não encontrado na base de dados customizada.</p></div>';
                    }
                }
                
                // Redireciona
                header('Location: ' . remove_query_arg(['edit_id', 'delete_id', 's', 'paged'], admin_url('admin.php?page=meu-pdv&tab=stock')));
                exit;
        }
    }
}

// =================================================================================
// Funções Auxiliares de Integração WooCommerce (Clientes e Pedidos)
// =================================================================================

/**
 * Cria ou busca um usuário/cliente no WooCommerce pelo CPF/Email.
 * @param string $cpf CPF do cliente.
 * @param array $customer_data Dados da tabela wpcrm_customers.
 * @return int ID do usuário WP/WC ou 0 se falhar.
 */
if (!function_exists('mpdm_get_or_create_wc_customer')) {
    function mpdm_get_or_create_wc_customer($cpf, $customer_data) {
        if (!mpdm_is_woocommerce_active()) {
            return 0;
        }
    
        $customer_id = 0;
        
        // 1. Tenta encontrar pelo CPF (como meta de usuário)
        $args = array(
            'meta_key'   => 'billing_cpf',
            'meta_value' => $cpf,
            'number'     => 1,
            'fields'     => 'ID'
        );
        $user_query = new WP_User_Query($args);
        $results = $user_query->get_results();

        if (!empty($results)) {
            return $results[0]; // Cliente/Usuário existente
        }

        // 2. Se não encontrou, tenta encontrar pelo email (se fornecido)
        if (!empty($customer_data['email'])) {
            $user = get_user_by('email', $customer_data['email']);
            if ($user) return $user->ID;
        }

        // 3. Cria como novo usuário/cliente
        $email = $customer_data['email'] ?? sanitize_email('pdv_' . time() . '@temp.com'); 
        $username = sanitize_user($customer_data['name'] ?? 'Cliente PDV') . '_' . time();
        $password = wp_generate_password(12);

        // Se o e-mail já existir, força a criação com um temporário
        if (email_exists($email)) {
            $email = sanitize_email('pdv_dup_' . time() . '@temp.com');
        }

        // Cria o usuário WP/WC
        $customer_id = wc_create_new_customer($email, $username, $password);

        if (!is_wp_error($customer_id)) {
            // Define o role correto
            $user = new WP_User($customer_id);
            $user->set_role('customer');
            
            // CORREÇÃO: Trata o nome nulo para evitar erro 'explode(): Passing null to parameter'
            $full_name = $customer_data['name'] ?? ''; // Garante string vazia
            $name_parts = explode(' ', $full_name, 2);
            $first_name = $name_parts[0] ?? 'Cliente';
            $last_name = $name_parts[1] ?? 'PDV';
            
            // Atualiza os metadados de cobrança para registrar o CPF e nome completo
            update_user_meta($customer_id, 'billing_first_name', $first_name);
            update_user_meta($customer_id, 'billing_last_name', $last_name);
            
            update_user_meta($customer_id, 'billing_cpf', $cpf);
            // Atualiza email se não for o temporário
            if ($customer_data['email']) {
                 update_user_meta($customer_id, 'billing_email', $customer_data['email']);
            }
            
            return $customer_id;
        }
        return 0;
    }
}

/**
 * Cria um pedido no WooCommerce a partir dos dados do PDV usando dados do CRM.
 * @param array $cart Dados do carrinho.
 * @param float $total_amount Valor total da venda.
 * @param string $customer_cpf CPF do cliente (se fornecido).
 * @param string $payment_method Método de pagamento.
 * @param float $amount_paid Valor pago.
 * @param array|null $customer_data Dados do cliente do WPCRM_TABLE.
 * @return int ID do pedido WC ou WP_Error.
 * @since 1.4.3
 */
if (!function_exists('mpdm_create_wc_order_with_crm_data')) {
    function mpdm_create_wc_order_with_crm_data($cart, $total_amount, $customer_cpf, $payment_method, $amount_paid, $customer_data) {
          if (!mpdm_is_woocommerce_active() || !function_exists('wc_create_order')) {
            return new WP_Error('wc_inactive', 'WooCommerce não está ativo ou funções essenciais ausentes.');
        }
        
        $customer_id = $customer_data['wc_user_id'] ?? 0; // Puxa o ID do WC do CRM
        
        // Cria o objeto do pedido
        $order = wc_create_order();
        
        // Define o cliente (se encontrado/criado)
        if ($customer_id > 0) {
            $order->set_customer_id($customer_id);
        }
        
        // Adiciona o endereço (usando dados do cliente CRM se disponíveis)
        $address = array(
            'first_name' => explode(' ', $customer_data['name'] ?? 'Cliente')[0] ?? 'Cliente',
            'last_name'  => explode(' ', $customer_data['name'], 2)[1] ?? 'PDV',
            'company'    => '',
            'email'      => $customer_data['email'] ?? '',
            // O WPPDV não salva telefone, mas mantém a estrutura
            'phone'      => '', 
            'address_1'  => $customer_data['address'] ?? 'Não Informado',
            'city'       => '',
            'state'      => '',
            'postcode'   => '',
            'country'    => 'BR'
        );
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
        
        $order->set_currency('BRL');
        
        // Adiciona os itens ao pedido (mantém a lógica de iteração do seu original)
        foreach ($cart as $item) {
            $item_price = floatval($item['price']);
            $item_quantity = intval($item['quantity']);
            $item_total_raw = $item_price * $item_quantity;
            $item_discount = (isset($item['discount']) ? $item_total_raw * (floatval($item['discount']) / 100) : 0);
            $item_total_final = $item_total_raw - $item_discount;
            
            if (isset($item['is_unregistered']) && $item['is_unregistered'] === true) {
                $line_item = new WC_Order_Item_Fee();
                $line_item->set_name($item['name']);
                $line_item->set_amount($item_total_final);
                $order->add_item($line_item);
            } else {
                $product_id = wc_get_product_id_by_sku($item['sku']);
                $product = wc_get_product($product_id);

                if ($product) {
                    $line_item_id = $order->add_product(
                        $product,
                        $item_quantity,
                        array(
                            'subtotal' => $item_total_raw,
                            'total'    => $item_total_final
                        )
                    );
                    
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        $order->get_item($line_item_id)->add_meta_data('_pdv_discount_percent', $item['discount'] . '%', true);
                        $order->get_item($line_item_id)->add_meta_data('_pdv_discount_amount', $item_discount, true);
                        $order->get_item($line_item_id)->save();
                    }
                }
            }
        }
        
        // Recalcula o total
        $order->calculate_totals(true);
        
        // Define o método de pagamento e transação
        $payment_title = ucfirst($payment_method) . ' (PDV)';
        $order->set_payment_method_title($payment_title);
        
        // Adiciona metadados do PDV
        $order->add_meta_data('_pdv_sale', 'yes', true);
        $order->add_meta_data('_pdv_customer_cpf', $customer_cpf, true);
        $order->add_meta_data('_pdv_amount_received', $amount_paid, true);
        
        // Define o status como Concluído
        $order->set_status('completed');
        $order->save();
        
        return $order->get_id();
    }
}


if (!function_exists('mpdm_get_product_by_identifier_callback')) {
    add_action('wp_ajax_mpdm_get_product_by_identifier', 'mpdm_get_product_by_identifier_callback');
    function mpdm_get_product_by_identifier_callback() {
        check_ajax_referer('mpdm-pos-nonce', 'security');
        
        $identifier = sanitize_text_field($_POST['identifier']);
        $product_data = null;

        if (mpdm_is_woocommerce_active()) {
            // Lógica de Busca no WooCommerce
            
            $product_wc = null;
            $product_id = wc_get_product_id_by_sku($identifier);
            if ($product_id) {
                $product_wc = wc_get_product($product_id);
            }
            
            // 2. Busca por meta customizada de Código de Barras (para produtos e variações)
            if (!$product_wc) {
                 global $wpdb;
                 // Busca por barcode_field em postmeta (para produtos simples ou variações)
                 $post_id_from_barcode = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_barcode_field' AND meta_value = %s", $identifier));
                 if ($post_id_from_barcode) {
                     $product_wc = wc_get_product($post_id_from_barcode);
                 }
            }
            
            if ($product_wc) {
                $parent_id = $product_wc->is_type('variation') ? $product_wc->get_parent_id() : $product_wc->get_id();
                $parent_product = $product_wc->is_type('variation') ? wc_get_product($parent_id) : $product_wc;
                
                 // É um produto simples, ou um produto variável pai (não usamos o pai na frente de caixa)
                 if ($product_wc->is_type('simple')) {
                     $product_data = [
                          'id'           => $product_wc->get_id(),
                          'name'         => $product_wc->get_name(),
                          'sku'          => $product_wc->get_sku(),
                          'price'        => $product_wc->get_price(),
                          'discount'     => $product_wc->get_meta('_pdv_discount') ?: 0,
                          'quantity'     => $product_wc->get_stock_quantity(),
                          'ncm'          => $product_wc->get_meta('_ncm_field') ?: '', // NOVO
                          'cest'         => $product_wc->get_meta('_cest_field') ?: '', // NOVO
                          'cfop'         => $product_wc->get_meta('_cfop_field') ?: '5102', // NOVO
                     ];
                 } 
                 // É uma variação
                 elseif ($product_wc->is_type('variation')) {
                     $parent = wc_get_product($product_wc->get_parent_id());
                     $attributes = $product_wc->get_variation_attributes();
                     $name_suffix = implode(', ', $attributes);
                     $product_data = [
                          'id'           => $product_wc->get_id(),
                          'name'         => $parent->get_name() . ' - ' . $name_suffix,
                          'sku'          => $product_wc->get_sku(),
                          'price'        => $product_wc->get_price(),
                          'discount'     => $product_wc->get_meta('_pdv_discount') ?: 0,
                          'quantity'     => $product_wc->get_stock_quantity(),
                          'ncm'          => $parent_product->get_meta('_ncm_field') ?: '', // NOVO: Puxa do Pai
                          'cest'         => $parent_product->get_meta('_cest_field') ?: '', // NOVO: Puxa do Pai
                          'cfop'         => $parent_product->get_meta('_cfop_field') ?: '5102', // NOVO: Puxa do Pai
                     ];
                 }
            }

        } else {
            // Lógica de Fallback com $wpdb (mantida)
            global $wpdb;
            $table_name = $wpdb->prefix . 'mpdm_products';
            
            $product_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE sku = %s OR barcode = %s", $identifier, $identifier), ARRAY_A);
            
            if ($product_row) {
                 if ($product_row['is_variable'] == 1) {
                      $variations = json_decode($product_row['variations'], true);
                      foreach ($variations as $variation) {
                          if ($variation['sku'] === $identifier || $variation['barcode'] === $identifier) {
                              $product_data = $variation;
                              $product_data['is_variable_child'] = true;
                              // NOVO: Adiciona campos fiscais do produto PAI
                              $product_data['ncm'] = $product_row['ncm'];
                              $product_data['cest'] = $product_row['cest'];
                              $product_data['cfop'] = $product_row['cfop'];
                              break;
                          }
                      }
                 } else {
                      $product_data = $product_row;
                      $product_data['ncm'] = $product_row['ncm']; // NOVO
                      $product_data['cest'] = $product_row['cest']; // NOVO
                      $product_data['cfop'] = $product_row['cfop']; // NOVO
                 }
            }
            
            if (!$product_data) {
                 $all_variable_products = $wpdb->get_results("SELECT * FROM " . $table_name . " WHERE is_variable = 1");
                 foreach ($all_variable_products as $var_prod) {
                      $variations = json_decode($var_prod->variations, true);
                      foreach ($variations as $variation) {
                          if ($variation['sku'] === $identifier || $variation['barcode'] === $identifier) {
                              $product_data = $variation;
                              $product_data['is_variable_child'] = true;
                              $product_data['ncm'] = $var_prod->ncm; // NOVO: Puxa do Pai
                              $product_data['cest'] = $var_prod->cest; // NOVO: Puxa do Pai
                              $product_data['cfop'] = $var_prod->cfop; // NOVO: Puxa do Pai
                              break 2;
                          }
                      }
                 }
            }
        }

        wp_send_json($product_data);
    }
}

/**
 * NOVO: Busca cliente na Tabela Central de Clientes (WPDDV_CRM_TABLE)
 * @since 1.4.3
 */
if (!function_exists('mpdm_get_customer_by_cpf_callback')) {
    add_action('wp_ajax_mpdm_get_customer_by_cpf', 'mpdm_get_customer_by_cpf_callback');
    function mpdm_get_customer_by_cpf_callback() {
        check_ajax_referer('mpdm-pos-nonce', 'security');
        global $wpdb;
        $customers_table = WPDDV_CRM_TABLE;
        $cpf = sanitize_text_field($_POST['cpf']);
        
        // Procura cliente na tabela unificada (WPCRM_TABLE)
        $customer_data = $wpdb->get_row($wpdb->prepare("SELECT name, cpf, discount_percent as discount FROM " . $customers_table . " WHERE cpf = %s", $cpf), ARRAY_A);
        
        // O campo na tabela unificada é 'discount_percent', mas o JS espera 'discount'
        if ($customer_data && isset($customer_data['discount_percent'])) {
            $customer_data['discount'] = $customer_data['discount_percent'];
            unset($customer_data['discount_percent']);
        }
        
        wp_send_json($customer_data);
    }
}


if (!function_exists('mpdm_finalize_sale_callback')) {
    add_action('wp_ajax_mpdm_finalize_sale', 'mpdm_finalize_sale_callback');
    function mpdm_finalize_sale_callback() {
        check_ajax_referer('mpdm-pos-nonce', 'security');
        global $wpdb;
        $products_table = $wpdb->prefix . 'mpdm_products';
        $sales_table = $wpdb->prefix . 'mpdm_sales';
        $customer_sales_table = $wpdb->prefix . 'mpdm_customer_sales';
        $fiscal_table = $wpdb->prefix . 'mpdm_fiscal'; // NOVO: Tabela Fiscal
        
        $cart = json_decode(stripslashes($_POST['cart']), true);
        $total_amount = floatval($_POST['total_amount']);
        $customer_cpf = sanitize_text_field($_POST['customer_cpf']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0.00; 
        
        // NOVO: Busca dados na tabela CRM Central de Clientes
        $customer_data = null;
        if (!empty($customer_cpf)) {
            // Usa WPDDV_CRM_TABLE (que aponta para WPCRM_TABLE se o plugin CRM estiver ativo)
            $customer_data = $wpdb->get_row($wpdb->prepare("SELECT wc_user_id, name, email, address FROM " . WPDDV_CRM_TABLE . " WHERE cpf = %s", $customer_cpf), ARRAY_A);
        }
        
        if (is_array($cart) && !empty($cart)) {
            $use_woocommerce = mpdm_is_woocommerce_active();
            $wc_order_id = 0;

            // 1. PROCESSAMENTO NO WOOCOMMERCE (SE ATIVO)
            if ($use_woocommerce) {
                // Cria o pedido WC (inclui a baixa de estoque do WC)
                $wc_order_id = mpdm_create_wc_order_with_crm_data($cart, $total_amount, $customer_cpf, $payment_method, $amount_paid, $customer_data);
                
                if (is_wp_error($wc_order_id)) {
                    error_log('PDV WC Error: ' . $wc_order_id->get_error_message());
                    wp_send_json_error('Erro ao criar pedido no WooCommerce: ' . $wc_order_id->get_error_message());
                }
            }
            
            // 2. BAIXA DE ESTOQUE (APENAS SE WOOCOMMERCE ESTIVER INATIVO)
            if (!$use_woocommerce) {
                foreach ($cart as $item) {
                    if (!isset($item['is_unregistered']) || $item['is_unregistered'] !== true) {
                        // Lógica de baixa de estoque em DB customizado
                        $parent_product_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . $products_table . " WHERE is_variable = 1 AND variations LIKE %s", '%' . $wpdb->esc_like($item['sku']) . '%'));
                        
                        if ($parent_product_id) {
                            $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $products_table . " WHERE id = %d", $parent_product_id));
                            $variations = json_decode($product->variations, true);
                            $total_quantity = 0;
                            foreach ($variations as &$variation) {
                                if ($variation['sku'] === $item['sku']) {
                                    $variation['quantity'] -= $item['quantity'];
                                }
                                $total_quantity += $variation['quantity']; 
                            }
                            $wpdb->update($products_table, ['variations' => json_encode($variations), 'quantity' => $total_quantity], ['id' => $parent_product_id]);
                        } else {
                            $wpdb->query($wpdb->prepare("UPDATE " . $products_table . " SET quantity = quantity - %d WHERE sku = %s AND is_variable = 0", $item['quantity'], $item['sku']));
                        }
                    }
                }
            }


            // 3. REGISTRO NA TABELA DE VENDAS DO PDV (MANTIDO)
            $data_to_insert = [
                'sale_date'      => current_time('mysql'),
                'total_amount'   => $total_amount,
                'amount_paid'    => $amount_paid,
                'payment_method' => $payment_method,
                'customer_cpf'   => !empty($customer_cpf) ? $customer_cpf : null,
                'items'          => json_encode($cart),
                // Adiciona o ID do pedido WC para rastreamento
                'wc_order_id'    => $wc_order_id, 
            ];
            
            $result = $wpdb->insert($sales_table, $data_to_insert);
            $sale_id = $wpdb->insert_id;
            
            /* NOVO: REGISTRO NA TABELA FISCAL (PRÉ-FATURAMENTO) */
            if ($sale_id) {
                $items_fiscal = [];
                // Processa o carrinho para gerar uma estrutura otimizada para NF-e/NFE.io
                foreach ($cart as $item) {
                    // Mapeamento dos campos fiscais (agora disponíveis no $item)
                    $items_fiscal[] = [
                        'sku' => $item['sku'],
                        'name' => $item['name'],
                        'quantity' => intval($item['quantity']),
                        'price' => floatval($item['price']),
                        'discount_percent' => floatval($item['discount'] ?? 0),
                        'ncm' => $item['ncm'] ?? '',
                        'cest' => $item['cest'] ?? '',
                        'cfop' => $item['cfop'] ?? '',
                        'unregistered' => $item['is_unregistered'] ?? false,
                    ];
                }
                
                $wpdb->insert(
                    $fiscal_table,
                    [
                        'sale_id' => $sale_id,
                        'sale_date' => $data_to_insert['sale_date'],
                        'customer_cpf' => $customer_cpf,
                        'total_amount' => $total_amount,
                        'items_fiscal' => json_encode($items_fiscal),
                        'nfe_status' => 'pendente', // Status inicial
                    ]
                );
            }

            // 4. REGISTRO NO HISTÓRICO DO CLIENTE (MANTIDO)
            if (!empty($customer_cpf) && $sale_id) {
                $wpdb->insert(
                    $customer_sales_table,
                    [
                        'customer_cpf' => $customer_cpf,
                        'sale_id'      => $sale_id,
                        'sale_date'    => current_time('mysql'),
                        'total_amount' => $total_amount,
                        'items'        => json_encode($cart)
                    ]
                );
            }

            if ($result === false) {
                error_log('PDV: Erro ao inserir venda no banco de dados. SQL Error: ' . $wpdb->last_error . ' SQL Query: ' . $wpdb->last_query);
                wp_send_json_error('Erro ao salvar a venda no histórico. Por favor, verifique o log de erros do WordPress para mais detalhes.');
            } else {
                wp_send_json_success('Venda finalizada com sucesso. WC Order ID: ' . $wc_order_id);
            }
        } else {
            wp_send_json_error('Carrinho vazio.');
        }
    }
}

// =================================================================================
// FUNÇÕES DE EXPORTAÇÃO (REMOVIDAS DA UI E AJUSTADAS PARA USO INTERNO)
// =================================================================================

// Funções de exportação individuais, sem o gancho 'admin_init' para não aparecer na UI.
// Elas são chamadas internamente pela função de exportação em massa.

if (!function_exists('mpdm_export_sales_csv_content')) {
    function mpdm_export_sales_csv_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mpdm_sales';
        // Seleciona explicitamente amount_paid
        $sales = $wpdb->get_results("SELECT id, sale_date, total_amount, amount_paid, payment_method, customer_cpf, items FROM " . $table_name . " ORDER BY sale_date DESC", ARRAY_A);
        
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para UTF-8
        
        // CORRIGIDO: Headers atualizados para incluir "Valor Pago"
        $headers = ['ID da Venda', 'Data', 'Total (R$)', 'Valor Pago (R$)', 'Método de Pagamento', 'Cliente (CPF)', 'Itens'];
        fputcsv($output, $headers, ';');

        foreach ($sales as $sale) {
            $items_formatted = '';
            $items = json_decode($sale['items'], true);
            if ($items) {
                foreach ($items as $item) {
                    // Calcula o preço final do item (com desconto) para o relatório
                    $item_subtotal = floatval($item['price']) * intval($item['quantity']);
                    $item_discount_amount = isset($item['discount']) ? $item_subtotal * (floatval($item['discount']) / 100) : 0;
                    $item_final_price = $item_subtotal - $item_discount_amount;
                    $discount_note = ($item_discount_amount > 0) ? " (Desc: {$item['discount']}%)" : "";
                    
                    $items_formatted .= "{$item['name']} (x{$item['quantity']}) - R$ " . number_format($item_final_price, 2, ',', '.') . $discount_note . " | ";
                }
            }
            
            $data = [
                $sale['id'],
                $sale['sale_date'],
                number_format($sale['total_amount'], 2, ',', '.'),
                number_format($sale['amount_paid'] ?? 0.00, 2, ',', '.'), // Incluindo amount_paid
                $sale['payment_method'],
                $sale['customer_cpf'] ?? 'N/A',
                trim($items_formatted, ' | ')
            ];

            fputcsv($output, $data, ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }
}

if (!function_exists('mpdm_export_products_csv_content')) {
    function mpdm_export_products_csv_content() {
        global $wpdb;
        $products_table = $wpdb->prefix . 'mpdm_products';
        $use_woocommerce = mpdm_is_woocommerce_active();
        
        $products_data = [];
        
        if ($use_woocommerce) {
            $args = array(
                'limit'  => -1,
                'status' => 'publish',
                'return' => 'ids',
            );
            $product_ids = (new WC_Product_Query($args))->get_products();
            
            foreach ($product_ids as $id) {
                $product_wc = wc_get_product($id);
                if (!$product_wc) continue;

                if ($product_wc->is_type('variable')) {
                    // Exporta variações
                    foreach ($product_wc->get_children() as $child_id) {
                        $variation = wc_get_product($child_id);
                        if (!$variation) continue;

                        $attributes = $variation->get_variation_attributes();
                        $name = $product_wc->get_name() . ' - ' . implode(', ', $attributes);
                        
                        $products_data[] = [
                             'id' => $id,
                             'name' => $name,
                             'sku' => $variation->get_sku(),
                             'barcode' => $variation->get_meta('_barcode_field') ?: '',
                             'price' => $variation->get_price(),
                             'discount' => $variation->get_meta('_pdv_discount') ?: 0,
                             'quantity' => $variation->get_stock_quantity(),
                             'validity_date' => $product_wc->get_meta('_validity_date') ?: '',
                             'is_variable' => 'Sim',
                         ];
                    }
                } else {
                    // Exporta produtos simples
                    $products_data[] = [
                         'id' => $id,
                         'name' => $product_wc->get_name(),
                         'sku' => $product_wc->get_sku(),
                         'barcode' => $product_wc->get_meta('_barcode_field') ?: '',
                         'price' => $product_wc->get_price(),
                         'discount' => $product_wc->get_meta('_pdv_discount') ?: 0,
                         'quantity' => $product_wc->get_stock_quantity(),
                         'validity_date' => $product_wc->get_meta('_validity_date') ?: '',
                         'is_variable' => 'Não',
                     ];
                }
            }

        } else {
            // Fallback com DB customizado (lógica original)
            $products = $wpdb->get_results("SELECT * FROM " . $products_table . " ORDER BY id DESC", ARRAY_A);
            
            foreach ($products as $product) {
                 if ($product['is_variable']) {
                      $variations = json_decode($product['variations'], true);
                      foreach ($variations as $variation) {
                          $products_data[] = [
                              'id' => $product['id'],
                              'name' => $variation['name'],
                              'sku' => $variation['sku'],
                              'barcode' => $variation['barcode'],
                              'price' => $variation['price'],
                              'discount' => $variation['discount'],
                              'quantity' => $variation['quantity'],
                              'validity_date' => $product['validity_date'],
                              'is_variable' => 'Sim',
                          ];
                      }
                 } else {
                      $products_data[] = [
                          'id' => $product['id'],
                          'name' => $product['name'],
                          'sku' => $product['sku'],
                          'barcode' => $product['barcode'],
                          'price' => $product['price'],
                          'discount' => $product['discount'],
                          'quantity' => $product['quantity'],
                          'validity_date' => $product['validity_date'],
                          'is_variable' => 'Não',
                      ];
                 }
            }
        }
        
        // Geração do CSV
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        $headers = ['ID', 'Nome', 'SKU', 'Código de Barras', 'Preço (R$)', 'Desconto (%)', 'Quantidade', 'Data de Validade', 'É Variável'];
        fputcsv($output, $headers, ';');
        
        foreach ($products_data as $product) {
            $data = [
                $product['id'],
                $product['name'],
                $product['sku'],
                $product['barcode'],
                number_format($product['price'], 2, ',', '.'),
                $product['discount'],
                $product['quantity'],
                $product['validity_date'],
                $product['is_variable'],
            ];
            fputcsv($output, $data, ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }
}

if (!function_exists('mpdm_export_customers_csv_content')) {
    function mpdm_export_customers_csv_content() {
        global $wpdb;
        $table_name = WPDDV_CRM_TABLE;
        // Puxa do CRM, que usa 'discount_percent'
        $customers = $wpdb->get_results("SELECT cpf, name, email, birthdate, sex, address, discount_percent FROM " . $table_name . " ORDER BY name ASC", ARRAY_A);
        
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Headers ajustados para o campo do CRM
        $headers = ['CPF', 'Nome', 'E-mail', 'Data de Nascimento', 'Sexo', 'Endereço', 'Desconto (%)'];
        fputcsv($output, $headers, ';');
        
        foreach ($customers as $customer) {
            $data = [
                $customer['cpf'],
                $customer['name'],
                $customer['email'],
                $customer['birthdate'],
                $customer['sex'],
                str_replace(["\r", "\n"], ' ', $customer['address']),
                $customer['discount_percent'],
            ];
            fputcsv($output, $data, ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }
}

// =================================================================================
// Funções AJAX para salvar configurações do usuário
// =================================================================================

add_action('wp_ajax_mpdm_save_zoom', 'mpdm_save_zoom_callback');
function mpdm_save_zoom_callback() {
    check_ajax_referer('mpdm-pos-nonce', 'security');
    
    if (isset($_POST['zoom_level'])) {
        $zoom_level = floatval($_POST['zoom_level']);
        if ($zoom_level >= 0.8 && $zoom_level <= 2.0) {
            update_user_meta(get_current_user_id(), 'mpdm_zoom_level', $zoom_level);
            wp_send_json_success();
        }
    }
    
    wp_send_json_error('Nível de zoom inválido.');
}

// =================================================================================
// Nova Função para Exportar TODOS os Relatórios para um ZIP
// =================================================================================
if (!function_exists('mpdm_export_all_reports_callback')) {
    add_action('wp_ajax_mpdm_export_all_reports', 'mpdm_export_all_reports_callback');
    function mpdm_export_all_reports_callback() {
        if (!current_user_can('mpdm_manage_pos')) {
            wp_send_json_error('Você não tem permissão para realizar esta ação.');
        }

        if (!class_exists('ZipArchive')) {
            wp_send_json_error('A extensão ZipArchive não está habilitada em seu servidor. Por favor, entre em contato com o suporte de sua hospedagem.');
        }

        $zip = new ZipArchive();
        $zip_filename = 'relatorios_pdv_' . date('Y-m-d_H-i-s') . '.zip';
        $zip_path = sys_get_temp_dir() . '/' . $zip_filename;

        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            wp_send_json_error('Não foi possível criar o arquivo ZIP.');
        }

        // Exportar Vendas
        $sales_content = mpdm_export_sales_csv_content();
        $zip->addFromString('historico_vendas.csv', $sales_content);

        // Exportar Produtos
        $products_content = mpdm_export_products_csv_content();
        $zip->addFromString('cadastro_produtos.csv', $products_content);

        // Exportar Clientes (do CRM Central)
        $customers_content = mpdm_export_customers_csv_content();
        $zip->addFromString('cadastro_clientes.csv', $customers_content);

        // Exportar Relatórios de Estoque Baixo e Produtos Vencendo
        global $wpdb;
        $products_table = $wpdb->prefix . 'mpdm_products';
        $expiration_days = intval(get_option('mpdm_expiration_days', 7));
        $expiration_date_limit = date('Y-m-d', strtotime("+" . $expiration_days . " days"));

        // Produtos com Estoque Baixo (Considerando WooCommerce se ativo)
        $low_stock_products_export = [];
        if (mpdm_is_woocommerce_active()) {
            $low_stock_args = array(
                'limit'     => -1,
                'status'    => 'publish',
                'orderby'   => 'meta_value_num',
                'order'     => 'ASC',
                'meta_key' => '_stock',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'       => '_stock',
                        'value'     => 10,
                        'compare' => '<',
                        'type'      => 'NUMERIC',
                    ),
                    array(
                        'key'       => '_manage_stock',
                        'value'     => 'yes',
                        'compare' => '=',
                    ),
                    array(
                        'key'       => '_virtual',
                        'value'     => 'yes',
                        'compare' => '!=',
                    ),
                ),
                'fields' => 'ids',
            );
            $low_stock_product_ids = (new WC_Product_Query($low_stock_args))->get_products();
            foreach ($low_stock_product_ids as $id) {
                $product = wc_get_product($id);
                if ($product) {
                    $low_stock_products_export[] = (object) ['name' => $product->get_name(), 'sku' => $product->get_sku(), 'quantity' => $product->get_stock_quantity()];
                }
            }
        } else {
             $low_stock_products_export = $wpdb->get_results("SELECT name, sku, quantity FROM " . $products_table . " WHERE quantity < 10 AND is_variable = 0 ORDER BY quantity ASC");
        }
        
        $low_stock_csv_handle = fopen('php://temp', 'r+');
        fprintf($low_stock_csv_handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Adiciona BOM para UTF-8
        fputcsv($low_stock_csv_handle, ['Nome', 'SKU', 'Quantidade'], ';');
        foreach ($low_stock_products_export as $prod) {
            fputcsv($low_stock_csv_handle, [$prod->name, $prod->sku, $prod->quantity], ';');
        }
        rewind($low_stock_csv_handle);
        $low_stock_csv = stream_get_contents($low_stock_csv_handle);
        $zip->addFromString('estoque_baixo.csv', $low_stock_csv);  
        fclose($low_stock_csv_handle);

        // Produtos Perto de Vencer (Considerando WooCommerce se ativo)
        $expiring_products_export = [];
        if (mpdm_is_woocommerce_active()) {
             $expiring_args = array(
                 'limit'     => -1,
                 'status'    => 'publish',
                 'meta_key' => '_validity_date',
                 'meta_value' => $expiration_date_limit,
                 'meta_compare' => '<=',
                 'orderby'   => 'meta_value',
                 'order'     => 'ASC',
                 'type'      => 'date',
                 'fields'    => 'ids',
               );
             $expiring_product_ids = (new WC_Product_Query($expiring_args))->get_products();
             foreach ($expiring_product_ids as $id) {
                 $product = wc_get_product($id);
                 if ($product) {
                     $expiring_products_export[] = (object) ['name' => $product->get_name(), 'sku' => $product->get_sku(), 'validity_date' => $product->get_meta('_validity_date')];
                 }
             }
        } else {
             $expiring_products_sql = $wpdb->prepare("SELECT name, sku, validity_date FROM " . $products_table . " WHERE validity_date <= %s AND validity_date IS NOT NULL ORDER BY validity_date ASC", $expiration_date_limit);
             $expiring_products_export = $wpdb->get_results($expiring_products_sql);
        }

        $expiring_csv_handle = fopen('php://temp', 'r+');
        fprintf($expiring_csv_handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Adiciona BOM para UTF-8
        fputcsv($expiring_csv_handle, ['Nome', 'SKU', 'Data de Vencimento'], ';');
        foreach ($expiring_products_export as $prod) {
            fputcsv($expiring_csv_handle, [$prod->name, $prod->sku, $prod->validity_date], ';');
        }
        rewind($expiring_csv_handle);
        $expiring_csv = stream_get_contents($expiring_csv_handle);
        $zip->addFromString('produtos_vencendo.csv', $expiring_csv);  
        fclose($expiring_csv_handle);
        
        $zip->close();

        // Enviar o arquivo para o navegador
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip_path) . '"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);

        // Limpar o arquivo temporário
        unlink($zip_path);
        
        exit;
    }
}

// =================================================================================
// Integração WooCommerce: Campos Fiscais
// =================================================================================

/**
 * Adiciona um painel (tab) para 'Dados Fiscais' na edição de produtos do WooCommerce.
 * @since 1.6.0
 */
if (!function_exists('mpdm_add_product_fiscal_tab')) {
    add_filter('woocommerce_product_data_tabs', 'mpdm_add_product_fiscal_tab');
    function mpdm_add_product_fiscal_tab($product_data_tabs) {
        if (mpdm_is_woocommerce_active()) {
            $product_data_tabs['fiscal_tab'] = array(
                'label' => __('Dados Fiscais (PDV)', 'WPPDV'),
                'target' => 'mpdm_fiscal_product_data',
                'class' => array('show_if_simple', 'show_if_variable'),
            );
        }
        return $product_data_tabs;
    }
}

/**
 * Adiciona o conteúdo à aba 'Dados Fiscais' para produtos simples e variáveis.
 * @since 1.6.0
 */
if (!function_exists('mpdm_add_product_fiscal_fields')) {
    add_action('woocommerce_product_data_panels', 'mpdm_add_product_fiscal_fields');
    function mpdm_add_product_fiscal_fields() {
        global $woocommerce, $post;
        
        if (!mpdm_is_woocommerce_active()) {
            return;
        }

        echo '<div id="mpdm_fiscal_product_data" class="panel woocommerce_options_panel">';
        echo '<h2>' . __('Informações Fiscais para NF-e', 'WPPDV') . '</h2>';

        // Campo NCM
        woocommerce_wp_text_input(
            array(
                'id' => '_ncm_field',
                'value' => get_post_meta($post->ID, '_ncm_field', true),
                'label' => __('NCM (Código Fiscal)', 'WPPDV'),
                'placeholder' => 'Ex: 8471.30.12 (8 dígitos)',
                'description' => __('Classificação Fiscal de Mercadorias. Obrigatório para NF-e.', 'WPPDV'),
                'custom_attributes' => array('required' => 'required'),
            )
        );

        // Campo CFOP
        woocommerce_wp_text_input(
            array(
                'id' => '_cfop_field',
                'value' => get_post_meta($post->ID, '_cfop_field', true) ?: '5102',
                'label' => __('CFOP Padrão', 'WPPDV'),
                'placeholder' => 'Ex: 5102 (Venda dentro do estado)',
                'description' => __('Código Fiscal de Operações e Prestações. Use o CFOP mais comum para este produto.', 'WPPDV'),
                'custom_attributes' => array('required' => 'required'),
            )
        );

        // Campo CEST
        woocommerce_wp_text_input(
            array(
                'id' => '_cest_field',
                'value' => get_post_meta($post->ID, '_cest_field', true),
                'label' => __('CEST (Substituição Tributária)', 'WPPDV'),
                'placeholder' => 'Ex: 21.053.00 (7 dígitos)',
                'description' => __('Código Especificador da Substituição Tributária (opcional, se aplicável).', 'WPPDV'),
            )
        );

        echo '</div>';
    }
}

/**
 * Salva os campos fiscais no produto simples e variável (via meta fields)
 * @since 1.6.0
 */
if (!function_exists('mpdm_save_product_fiscal_fields')) {
    add_action('woocommerce_process_product_meta_simple', 'mpdm_save_product_fiscal_fields');
    add_action('woocommerce_process_product_meta_variable', 'mpdm_save_product_fiscal_fields');
    function mpdm_save_product_fiscal_fields($post_id) {
        if (!mpdm_is_woocommerce_active()) {
            return;
        }

        $ncm = sanitize_text_field($_POST['_ncm_field'] ?? '');
        $cfop = sanitize_text_field($_POST['_cfop_field'] ?? '');
        $cest = sanitize_text_field($_POST['_cest_field'] ?? '');

        // Salva as metas no produto principal
        update_post_meta($post_id, '_ncm_field', $ncm);
        update_post_meta($post_id, '_cfop_field', $cfop);
        update_post_meta($post_id, '_cest_field', $cest);
    }
}