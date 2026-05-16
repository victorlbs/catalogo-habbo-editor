<?php
// index.php
require 'conexao.php';

// ==========================================
// MÓDULO AJAX (EDIÇÃO EM TEMPO REAL)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['ajax_action'] === 'edit_item') {
            $stmt1 = $pdo->prepare("UPDATE catalog_items SET catalog_name=?, cost_credits=?, cost_points=?, points_type=?, amount=? WHERE id=?");
            $stmt1->execute([$_POST['catalog_name'], $_POST['cost_credits'], $_POST['cost_points'], $_POST['points_type'], $_POST['amount'], $_POST['catalog_id']]);

            $stmt2 = $pdo->prepare("UPDATE items_base SET public_name=?, sprite_id=?, width=?, length=?, stack_height=?, allow_walk=?, allow_sit=?, allow_lay=?, allow_stack=?, interaction_type=?, vending_ids=? WHERE id=?");
            $stmt2->execute([$_POST['public_name'], $_POST['sprite_id'], $_POST['width'], $_POST['length'], $_POST['stack_height'], $_POST['allow_walk'], $_POST['allow_sit'], $_POST['allow_lay'], $_POST['allow_stack'], $_POST['interaction_type'], $_POST['vending_ids'], $_POST['base_id']]);
            
            echo json_encode(['success' => true]);
        } 
        elseif ($_POST['ajax_action'] === 'edit_base_only') {
            $stmt2 = $pdo->prepare("UPDATE items_base SET public_name=?, sprite_id=?, width=?, length=?, stack_height=?, allow_walk=?, allow_sit=?, allow_lay=?, allow_stack=?, interaction_type=?, vending_ids=? WHERE id=?");
            $stmt2->execute([$_POST['public_name'], $_POST['sprite_id'], $_POST['width'], $_POST['length'], $_POST['stack_height'], $_POST['allow_walk'], $_POST['allow_sit'], $_POST['allow_lay'], $_POST['allow_stack'], $_POST['interaction_type'], $_POST['vending_ids'], $_POST['base_id']]);
            
            echo json_encode(['success' => true]);
        }
        elseif ($_POST['ajax_action'] === 'edit_page') {
            $stmt = $pdo->prepare("UPDATE catalog_pages SET caption=?, caption_save=?, parent_id=?, icon_image=?, page_layout=?, order_num=?, page_headline=?, page_text1=?, page_text2=?, page_text_details=? WHERE id=?");
            $caption_save = strtolower(str_replace(' ', '_', $_POST['caption']));
            $stmt->execute([$_POST['caption'], $caption_save, $_POST['parent_id'], $_POST['icon_image'], $_POST['page_layout'], $_POST['order_num'], $_POST['page_headline'], $_POST['page_text1'], $_POST['page_text2'], $_POST['page_text_details'], $_POST['page_id']]);
            echo json_encode(['success' => true]);
        }
        elseif ($_POST['ajax_action'] === 'reorder_pages') {
            if (isset($_POST['page_ids']) && is_array($_POST['page_ids'])) {
                $stmt = $pdo->prepare("UPDATE catalog_pages SET order_num=? WHERE id=?");
                foreach ($_POST['page_ids'] as $index => $id) {
                    $stmt->execute([$index + 1, $id]);
                }
            }
            echo json_encode(['success' => true]);
        }
        elseif ($_POST['ajax_action'] === 'rename_page') {
            $stmt = $pdo->prepare("UPDATE catalog_pages SET caption=?, caption_save=? WHERE id=?");
            $caption_save = strtolower(str_replace(' ', '_', $_POST['caption']));
            $stmt->execute([$_POST['caption'], $caption_save, $_POST['page_id']]);
            echo json_encode(['success' => true, 'new_caption' => htmlspecialchars($_POST['caption'])]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// AÇÕES DO FORMULÁRIO PADRÃO (Criar Nova Página)
// ==========================================
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_page') {
    $parent_id = $_POST['parent_id'];
    $caption = $_POST['caption'];
    $page_layout = $_POST['page_layout'];
    $icon_color = $_POST['icon_color'] ?? 1;
    $icon_image = $_POST['icon_image'];
    $min_rank = $_POST['min_rank'] ?? 1;
    $order_num = $_POST['order_num'];

    $stmt = $pdo->prepare("INSERT INTO catalog_pages (parent_id, caption, caption_save, page_layout, icon_color, icon_image, min_rank, order_num, visible, enabled, club_only, vip_only) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '1', '1', '0', '0')");
    if ($stmt->execute([$parent_id, $caption, strtolower(str_replace(' ', '_', $caption)), $page_layout, $icon_color, $icon_image, $min_rank, $order_num])) {
        $mensagem = "<div class='alert success'>Página '$caption' criada com sucesso!</div>";
    }
}

// ==========================================
// ESTRUTURAÇÃO DAS PÁGINAS EM ÁRVORE
// ==========================================
$stmtPages = $pdo->query("SELECT * FROM catalog_pages ORDER BY order_num ASC, id ASC");
$allPages = $stmtPages->fetchAll();

$topTabs = [];
$subPages = []; 

foreach ($allPages as $p) {
    if ($p['parent_id'] == -1) {
        $topTabs[] = $p;
    } else {
        $subPages[$p['parent_id']][] = $p;
    }
}

$activeTabId = isset($_GET['tab']) ? $_GET['tab'] : (count($topTabs) > 0 ? $topTabs[0]['id'] : -1);
$activePageId = isset($_GET['page']) ? (int)$_GET['page'] : -1;

$activePath = [];
$curr = $activePageId;
while ($curr != -1 && $curr != 0) {
    $activePath[] = $curr;
    $parent = -1;
    foreach ($allPages as $p) {
        if ($p['id'] == $curr) {
            $parent = $p['parent_id'];
            break;
        }
    }
    if ($parent == -1 || in_array($parent, $activePath)) break;
    $curr = $parent;
}

function drawSidebarMenu($parentId, $subPages, $activeTabId, $activePageId, $activePath, $level = 0) {
    if (!isset($subPages[$parentId])) return;
    
    echo "<div class='sortable-level'>"; 
    foreach ($subPages[$parentId] as $sp) {
        $isActive = ($activePageId == $sp['id']);
        $hasChildren = isset($subPages[$sp['id']]);
        $isOpen = in_array($sp['id'], $activePath); 
        $padding = 10 + ($level * 15);
        
        echo "<div class='menu-item-container draggable-subpage' data-id='{$sp['id']}'>";
        // AQUI: Adicionado onclick='handleTabClick(...)' e removido ondblclick do SPAN
        echo "<a href='?tab={$activeTabId}&page={$sp['id']}' class='" . ($isActive ? 'active' : '') . "' style='padding-left: {$padding}px;' onclick='handleTabClick(event, this.href, {$sp['id']}, this)'>";
        
        if ($hasChildren) {
            $arrow = $isOpen ? '▼' : '▶';
            echo "<span class='toggle-btn' onclick='toggleMenu(event, \"submenu-{$sp['id']}\", this)'>{$arrow}</span>";
        } else {
            echo "<span style='display:inline-block; width:15px; margin-right:5px;'></span>";
        }
        
        echo "<img src='https://habbinfo.top/swf/c_images/catalogue/icon_{$sp['icon_image']}.png' class='page-icon' onerror=\"this.src='https://habbinfo.top/swf/c_images/catalogue/icon_1.png'\"> ";
        echo "<span class='editable-name' title='Duplo clique para renomear'>" . htmlspecialchars($sp['caption']) . "</span>";
        echo "</a>";

        if ($hasChildren) {
            $display = $isOpen ? "block" : "none";
            echo "<div id='submenu-{$sp['id']}' class='submenu-container' style='display: {$display};'>";
            drawSidebarMenu($sp['id'], $subPages, $activeTabId, $activePageId, $activePath, $level + 1);
            echo "</div>";
        }
        
        echo "</div>"; 
    }
    echo "</div>"; 
}

// ==========================================
// BUSCA OS DADOS DA PÁGINA ATUAL E ITENS
// ==========================================
$itensDaPagina = [];
$paginaSelecionadaNome = 'Selecione uma página';
$activePageData = null;

if ($activeTabId === 'orphans') {
    $stmtOrphans = $pdo->query("
        SELECT b.id AS base_id, b.sprite_id, b.public_name, b.item_name, b.type, b.width, b.length, b.stack_height, b.allow_stack, b.allow_sit, b.allow_lay, b.allow_walk, b.allow_gift, b.allow_trade, b.allow_recycle, b.allow_marketplace_sell, b.allow_inventory_stack, b.interaction_type, b.interaction_modes_count, b.vending_ids, b.multiheight, b.customparams, b.effect_id_male, b.effect_id_female, b.clothing_on_walk 
        FROM items_base b LEFT JOIN catalog_items c ON b.id = c.item_ids WHERE c.id IS NULL ORDER BY b.id DESC LIMIT 300
    ");
    $itensDaPagina = $stmtOrphans->fetchAll();
} else if ($activePageId != -1 && is_numeric($activeTabId)) {
    foreach ($allPages as $p) {
        if ($p['id'] == $activePageId) {
            $activePageData = $p;
            $paginaSelecionadaNome = $p['caption'];
            break;
        }
    }
    $stmtItens = $pdo->prepare("
        SELECT c.id AS catalog_id, c.catalog_name, c.cost_credits, c.cost_points, c.points_type, c.amount, b.id AS base_id, b.sprite_id, b.public_name, b.item_name, b.type, b.width, b.length, b.stack_height, b.allow_stack, b.allow_sit, b.allow_lay, b.allow_walk, b.allow_gift, b.allow_trade, b.allow_recycle, b.allow_marketplace_sell, b.allow_inventory_stack, b.interaction_type, b.interaction_modes_count, b.vending_ids, b.multiheight, b.customparams, b.effect_id_male, b.effect_id_female, b.clothing_on_walk 
        FROM catalog_items c LEFT JOIN items_base b ON c.item_ids = b.id WHERE c.page_id = ? ORDER BY c.order_number ASC
    ");
    $stmtItens->execute([$activePageId]);
    $itensDaPagina = $stmtItens->fetchAll();
}

// ==========================================
// LER IMAGENS DA PASTA CATALOGUE
// ==========================================
$caminhoPastaImagens = 'C:\xampp\htdocs\CATA\catalogue'; 
$imagensDisponiveis = [];
if (is_dir($caminhoPastaImagens)) {
    $arquivos = scandir($caminhoPastaImagens);
    foreach ($arquivos as $arquivo) {
        $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
        if ($extensao === 'gif' || $extensao === 'png') {
            $imagensDisponiveis[] = pathinfo($arquivo, PATHINFO_FILENAME);
        }
    }
    $imagensDisponiveis = array_unique($imagensDisponiveis);
    sort($imagensDisponiveis);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Catálogo Editor em Tempo Real</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Ubuntu', sans-serif; background: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #000; }

        .catalog-window { width: 1100px; height: 750px; background: #fff; border-radius: 6px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); display: flex; flex-direction: column; overflow: hidden; border: 2px solid #1a1a1a;}
        
        .header { background: #1c7b9c; border-bottom: 2px solid #1a1a1a; padding: 6px 10px; display: flex; justify-content: center; align-items: center; position: relative; }
        .header h1 { color: #fff; font-size: 14px; font-weight: bold; text-shadow: 1px 1px 0 #0f4f66; }
        .btn-close { position: absolute; right: 8px; top: 4px; background: #c22b2b; color: white; border: 1px solid #1a1a1a; border-radius: 3px; width: 22px; height: 22px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center;}
        
        .top-tabs { display: flex; background: #e3e3ea; border-bottom: 2px solid #1a1a1a; overflow-x: auto; }
        .top-tabs a { flex: 1; text-align: center; padding: 8px 10px; text-decoration: none; color: #333; font-size: 13px; border-right: 1px solid #c8c8d3; min-width: 100px; white-space: nowrap;}
        .top-tabs a.active { background: #fff; font-weight: bold; border-bottom: 2px solid #fff; margin-bottom: -2px;}
        .top-tabs a.btn-add { background: #2ecc71; color: white; font-weight: bold; border-right: none;}
        .top-tabs a.btn-orphan { background: #f39c12; color: white; font-weight: bold; border-right: 1px solid #1a1a1a;}
        
        .draggable-tab, .draggable-subpage { cursor: grab; }
        .draggable-tab:active, .draggable-subpage:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.5; background: #dcdcdc; border: 1px dashed #777; }

        .editable-name { display: inline-block; padding: 1px 4px; border-radius: 3px; transition: background 0.2s; border: 1px solid transparent; }
        .editable-name:hover { background: rgba(0,0,0,0.08); border-color: rgba(0,0,0,0.1); cursor: text;}
        .inline-edit-input { font-family: 'Ubuntu', sans-serif; font-size: 13px; font-weight: inherit; color: #333; background: #fff; border: 1px solid #3498db; border-radius: 3px; padding: 1px 4px; outline: none; box-shadow: 0 0 3px rgba(52,152,219,0.5); width: auto; min-width: 60px;}

        .banner { background: #155a73 url('https://images.habbo.com/c_images/catalogue/catalog_header_habbo_club.gif') no-repeat right; height: 80px; display: flex; align-items: center; padding: 0 20px; border-bottom: 2px solid #1a1a1a;}
        .banner h2 { color: #fff; font-size: 20px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5); }

        .content-area { display: flex; flex: 1; background: #e3e3ea; overflow: hidden; }
        
        .sidebar { width: 260px; background: #fff; border-right: 2px solid #1a1a1a; display: flex; flex-direction: column;}
        .search-box { padding: 10px; background: #f4f4f4; border-bottom: 1px solid #ccc; display: flex;}
        .search-box input { flex: 1; padding: 6px; border: 1px solid #ccc; border-radius: 3px 0 0 3px; font-size: 12px; }
        .search-box button { background: #1c7b9c; border: 1px solid #1c7b9c; border-radius: 0 3px 3px 0; color: white; padding: 0 10px; cursor: pointer;}
        
        .sub-pages-list { flex: 1; overflow-y: auto; list-style: none; overflow-x: hidden; }
        
        .menu-item-container a { padding: 6px 10px; text-decoration: none; color: #333; font-size: 13px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 4px; }
        .menu-item-container a:hover { background: #f9f9f9; }
        .menu-item-container a.active { background: #4cb3d4; color: #fff; font-weight: bold; }
        .page-icon { width: 20px; height: auto; object-fit: contain; }
        
        .toggle-btn { 
            display: inline-flex; align-items: center; justify-content: center;
            width: 15px; height: 15px; background: #e3e3e3; border-radius: 3px;
            font-size: 9px; cursor: pointer; color: #555; margin-right: 5px;
        }
        .toggle-btn:hover { background: #ccc; color: #000; }
        .menu-item-container a.active .toggle-btn { background: #3b8ea8; color: #fff; }

        .main-panel { flex: 1; background: #fff; padding: 15px; overflow-y: auto; }
        
        .page-info-box { background: #959595; border: 2px solid #a8a8a8; border-radius: 5px; padding: 15px; margin-bottom: 20px; color: #1a1a1a; display: flex; gap: 15px; box-shadow: inset 1px 1px 3px rgba(0,0,0,0.1);}
        .page-info-box img.headline-img { max-width: 150px; object-fit: contain; align-self: flex-start;}
        .page-info-texts { flex: 1; font-size: 13px; line-height: 1.4;}
        .page-info-texts h4 { color: #fff; font-size: 18px; text-shadow: 1px 1px 0 rgba(0,0,0,0.3); margin-bottom: 8px;}
        .btn-edit-page { background: #2c3e50; color: #fff; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 11px; margin-top: 10px;}
        
        .page-edit-form { display: none; flex: 1; flex-direction: column; gap: 8px; }
        .page-edit-form input, .page-edit-form textarea, .page-edit-form select { width: 100%; padding: 5px; font-size: 12px; border: 1px solid #ccc; border-radius: 3px; font-family: 'Ubuntu';}
        .page-edit-form textarea { height: 50px; resize: vertical; }
        .edit-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .edit-grid label { font-size: 10px; font-weight: bold; margin-bottom: 2px; display: block; color: #333; }

        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-top: 15px; }
        .item-card { background: #ececec; border: 1px solid #c0c0c0; border-radius: 4px; padding: 10px; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative;}
        .item-card.has-details { border-bottom-left-radius: 0; border-bottom-right-radius: 0; background: #e0e0e0;}
        .item-icon { height: 50px; object-fit: contain; margin-bottom: 10px; }
        .item-title { font-size: 11px; font-weight: bold; margin-bottom: 5px; word-break: break-word;}
        .price-tags { display: flex; flex-direction: column; gap: 3px; margin-bottom: 10px; width: 100%;}
        .price { background: #f1c40f; color: #fff; padding: 2px 5px; border-radius: 3px; font-size: 10px; font-weight: bold; display: flex; align-items: center; justify-content: center;}
        .price.diamonds { background: #9b59b6; }
        .price.duckets { background: #e67e22; }

        .item-badges { position: absolute; top: 5px; right: 5px; display: flex; gap: 3px; }
        .badge-icon { background: rgba(255, 255, 255, 0.9); border: 1px solid #bdc3c7; border-radius: 3px; font-size: 10px; padding: 2px; cursor: help; box-shadow: 0 1px 2px rgba(0,0,0,0.1);}

        .btn-config { background: #1c7b9c; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer; font-size: 11px; font-weight: bold; width: 100%;}
        .btn-config.orange { background: #e67e22; }
        .btn-config.orange:hover { background: #d35400; }
        
        .details-panel { display: none; background: #2c3e50; color: #ecf0f1; border: 1px solid #1a1a1a; border-top: none; border-radius: 0 0 4px 4px; padding: 15px; grid-column: 1 / -1; margin-bottom: 10px;}
        .editor-container { display: flex; gap: 15px; }
        .editor-form { flex: 1; }
        .editor-preview { width: 300px; background: #a2b490; border: 2px solid #5d6d4f; border-radius: 5px; position: relative; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        
        .room-floor { position: absolute; width: 300px; height: 300px; background-size: 32px 16px; background-image: linear-gradient(to right, rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,0.1) 1px, transparent 1px); transform: rotateX(60deg) rotateZ(45deg); top: 50px; }
        .sim-mobi { position: absolute; bottom: 50%; left: 50%; transform: translateX(-50%); transition: all 0.3s ease; z-index: 2;}
        .sim-avatar { position: absolute; width: 30px; height: 60px; background: url('https://www.habbo.com/habbo-imaging/avatarimage?figure=hd-180-1.ch-210-66.lg-270-82.sh-290-91&action=std&direction=2&head_direction=2&img_format=png') no-repeat center bottom; z-index: 3; transition: all 0.3s ease; left: 50%; bottom: 50%; transform: translateX(-50%); pointer-events: none;}
        .sim-avatar.sit { background: url('https://www.habbo.com/habbo-imaging/avatarimage?figure=hd-180-1.ch-210-66.lg-270-82.sh-290-91&action=sit&direction=2&head_direction=2&img_format=png') no-repeat center bottom; }
        .sim-avatar.lay { background: url('https://www.habbo.com/habbo-imaging/avatarimage?figure=hd-180-1.ch-210-66.lg-270-82.sh-290-91&action=lay&direction=2&head_direction=2&img_format=png') no-repeat center bottom; transform: translateX(-50%) rotate(-90deg) translateY(15px); }

        .details-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
        .details-grid div { background: #34495e; padding: 6px; border-radius: 3px; border-left: 3px solid #3498db; }
        .details-grid span { color: #bdc3c7; display: block; font-size: 9px; text-transform: uppercase; margin-bottom: 3px;}
        .details-grid input, .details-grid select { width: 100%; padding: 4px; border: 1px solid #1c2833; border-radius: 2px; font-size: 11px; background: #ecf0f1; color: #000; font-family: 'Ubuntu';}
        .section-title { grid-column: 1 / -1; font-size: 12px; font-weight: bold; border-bottom: 1px solid #7f8c8d; padding-bottom: 3px; margin-top: 5px; color: #f1c40f; }
        
        .btn-save { background: #27ae60; color: white; border: none; padding: 8px; border-radius: 3px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 15px; font-size: 12px; transition: background 0.2s;}
        .btn-save:hover { background: #2ecc71; }

        .admin-form { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .admin-form label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;}
        .admin-form input, .admin-form select { width: 100%; padding: 8px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 3px;}
        .admin-form button { background: #2ecc71; color: white; border: none; padding: 10px; width: 100%; font-weight: bold; cursor: pointer;}
    </style>
</head>
<body>

<div class="catalog-window">
    <div class="header"><h1>Loja</h1><button class="btn-close">X</button></div>

    <div class="top-tabs" id="sortable-tabs">
        <?php foreach ($topTabs as $tab): ?>
            <!-- AQUI: Adicionado onclick e removido ondblclick do span -->
            <a href="?tab=<?= $tab['id'] ?>" class="draggable-tab <?= $activeTabId == $tab['id'] ? 'active' : '' ?>" data-id="<?= $tab['id'] ?>" onclick="handleTabClick(event, this.href, <?= $tab['id'] ?>, this)">
                <span class="editable-name" title="Duplo clique para renomear"><?= htmlspecialchars($tab['caption']) ?></span>
            </a>
        <?php endforeach; ?>
        <a href="?tab=orphans" class="btn-orphan ignore-drag <?= $activeTabId === 'orphans' ? 'active' : '' ?>">📦 Sem Catálogo</a>
        <a href="?tab=new" class="btn-add ignore-drag <?= $activeTabId === 'new' ? 'active' : '' ?>">⚙️ Nova Aba</a>
    </div>

    <div class="banner"><h2>Catálogo Manager</h2></div>

    <div class="content-area">
        <?php if ($activeTabId === 'new'): ?>
            <div class="main-panel">
                <h3>⚙️ Adicionar Nova Página ao Catálogo</h3>
                <?= $mensagem ?>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="add_page">
                    <label>Nome da Página</label><input type="text" name="caption" required>
                    <label>Página Pai</label>
                    <select name="parent_id">
                        <option value="-1">- Aba Principal do Topo -</option>
                        <?php foreach ($allPages as $p): ?>
                            <option value="<?= $p['id'] ?>">Sub-página de: <?= htmlspecialchars($p['caption']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;"><label>ID Ícone</label><input type="number" name="icon_image" value="1"></div>
                        <div style="flex: 1;"><label>Layout</label><select name="page_layout"><option value="default_3x3">default_3x3</option><option value="club_buy">club_buy</option><option value="frontpage">frontpage</option></select></div>
                        <div style="flex: 1;"><label>Ordem</label><input type="number" name="order_num" value="1"></div>
                    </div>
                    <button type="submit">Criar Página</button>
                </form>
            </div>
            
        <?php else: ?>
        
            <?php if ($activeTabId !== 'orphans'): ?>
            <div class="sidebar">
                <div class="search-box"><input type="text" placeholder="Procurar"><button>🔍</button></div>
                <div class="sub-pages-list" id="sidebar-menu-wrapper">
                    <?php drawSidebarMenu($activeTabId, $subPages, $activeTabId, $activePageId, $activePath, 0); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="main-panel" <?= $activeTabId === 'orphans' ? 'style="flex:1;"' : '' ?>>
                <?php if ($activeTabId === 'orphans'): ?>
                    <h3 style="color: #d35400;">📦 Mobis na Base de Dados (Não estão à venda)</h3>
                <?php elseif ($activePageId == -1): ?>
                    <div style="background: #f9f9f9; padding: 20px; text-align: center; color: #777; margin-top: 20px;">Clique em uma página no menu ao lado.</div>
                <?php else: ?>
                    
                    <div class="page-info-box">
                        <div id="header-img-container">
                            <?php $hl = $activePageData['page_headline'] ?? ''; ?>
                            <img id="current-headline-img" 
                                 src="<?= $hl ? '/CATA/catalogue/' . htmlspecialchars($hl) . '.png' : '' ?>" 
                                 onerror="if(this.src.indexOf('.png') !== -1) { this.src = this.src.replace('.png', '.gif'); } else { this.style.display = 'none'; }" 
                                 class="headline-img" 
                                 style="<?= empty($hl) ? 'display: none;' : 'display: block;' ?>" 
                                 alt="Imagem do Topo">
                        </div>
                        
                        <div class="page-info-texts" id="page-text-view" style="width: 100%;">
                            <h4><?= htmlspecialchars($activePageData['caption']) ?></h4>
                            <p><?= nl2br(htmlspecialchars($activePageData['page_text1'])) ?></p>
                            <p><?= nl2br(htmlspecialchars($activePageData['page_text2'])) ?></p>
                            <p style="font-size: 11px; font-style: italic; opacity: 0.8;"><?= nl2br(htmlspecialchars($activePageData['page_text_details'])) ?></p>
                            <button class="btn-edit-page" onclick="togglePageEdit()">✏️ Editar Página Completa</button>
                        </div>

                        <form id="page-text-edit" class="page-edit-form" onsubmit="savePageAjax(event)">
                            <input type="hidden" name="page_id" value="<?= $activePageData['id'] ?>">
                            <div class="edit-grid">
                                <div><label>Título (Caption)</label><input type="text" name="caption" value="<?= htmlspecialchars($activePageData['caption']) ?>" required></div>
                                <div>
                                    <label>Página Pai</label>
                                    <select name="parent_id">
                                        <option value="-1" <?= $activePageData['parent_id'] == -1 ? 'selected' : '' ?>>- Topo -</option>
                                        <?php foreach ($allPages as $p): if($p['id'] == $activePageData['id']) continue; ?>
                                            <option value="<?= $p['id'] ?>" <?= $activePageData['parent_id'] == $p['id'] ? 'selected' : '' ?>>ID <?= $p['id'] ?> - <?= htmlspecialchars($p['caption']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div><label>ID do Ícone</label><input type="number" name="icon_image" value="<?= $activePageData['icon_image'] ?>"></div>
                                <div><label>Ordem</label><input type="number" name="order_num" value="<?= $activePageData['order_num'] ?>"></div>
                            </div>
                            
                            <div style="display: flex; gap: 8px;">
                                <div style="flex: 1;">
                                    <label style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">Layout</label>
                                    <select name="page_layout">
                                        <option value="default_3x3" <?= $activePageData['page_layout'] == 'default_3x3' ? 'selected' : '' ?>>default_3x3</option>
                                        <option value="club_buy" <?= $activePageData['page_layout'] == 'club_buy' ? 'selected' : '' ?>>club_buy</option>
                                        <option value="frontpage" <?= $activePageData['page_layout'] == 'frontpage' ? 'selected' : '' ?>>frontpage</option>
                                        <option value="roomads" <?= $activePageData['page_layout'] == 'roomads' ? 'selected' : '' ?>>roomads</option>
                                        <option value="pets" <?= $activePageData['page_layout'] == 'pets' ? 'selected' : '' ?>>pets</option>
                                    </select>
                                </div>
                                <div style="flex: 2;">
                                    <label style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">Imagem do Topo</label>
                                    <select name="page_headline" id="select_headline" onchange="previewHeadline()">
                                        <option value="">- Nenhuma -</option>
                                        <?php foreach ($imagensDisponiveis as $imgName): ?>
                                            <option value="<?= htmlspecialchars($imgName) ?>" <?= ($activePageData['page_headline'] == $imgName) ? 'selected' : '' ?>><?= htmlspecialchars($imgName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <textarea name="page_text1" placeholder="Texto 1 (Introdução)"><?= htmlspecialchars($activePageData['page_text1']) ?></textarea>
                            <textarea name="page_text2" placeholder="Texto 2 (Lista/Instruções)"><?= htmlspecialchars($activePageData['page_text2']) ?></textarea>
                            <textarea name="page_text_details" placeholder="Texto de Rodapé (Links, avisos)"><?= htmlspecialchars($activePageData['page_text_details']) ?></textarea>
                            <div style="display:flex; gap:5px;">
                                <button type="submit" class="btn-save" id="btn-save-page" style="margin-top:0;">💾 Salvar Página</button>
                                <button type="button" class="btn-close" style="position:static; width:auto; padding: 8px; border-radius: 3px;" onclick="togglePageEdit()">Cancelar</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (count($itensDaPagina) > 0 && ($activePageId != -1 || $activeTabId === 'orphans')): ?>
                    <div class="items-grid">
                        <?php foreach ($itensDaPagina as $item): ?>
                            <?php $isOrphan = ($activeTabId === 'orphans'); $uniqueId = $isOrphan ? $item['base_id'] : $item['catalog_id']; ?>
                            
                            <div class="item-card" id="card-<?= $uniqueId ?>" <?= $isOrphan ? 'style="background: #fdf2e9; border-color: #f39c12;"' : '' ?>>
                                <div class="item-badges">
                                    <?php if($item['allow_sit'] == 1): ?><span class="badge-icon" title="Pode Sentar">🪑</span><?php endif; ?>
                                    <?php if($item['allow_lay'] == 1): ?><span class="badge-icon" title="Pode Deitar">🛏️</span><?php endif; ?>
                                    <?php if($item['allow_walk'] == 1): ?><span class="badge-icon" title="Pode Andar Por Cima">👣</span><?php endif; ?>
                                </div>
                                <img src="https://cdn.comprahabbo.com/swf/dcr/hof_furni/<?= htmlspecialchars($item['item_name']) ?>_icon.png" class="item-icon" onerror="this.src='https://cdn.comprahabbo.com/swf/c_images/catalogue/icon_1.png'" id="img-source-<?= $uniqueId ?>">
                                <div class="item-title" <?= $isOrphan ? 'style="color:#d35400;"' : '' ?>><?= htmlspecialchars($item['public_name']) ?></div>
                                
                                <?php if(!$isOrphan): ?>
                                    <div class="price-tags">
                                        <span class="price"><?= $item['cost_credits'] ?> Créditos</span>
                                        <?php if($item['cost_points'] > 0) { $c = $item['points_type']==5?'diamonds':'duckets'; $n = $item['points_type']==5?'Dia':'Dck'; echo "<span class='price $c'>{$item['cost_points']} $n</span>"; } ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 9px; color: #777; margin-bottom: 10px;">ID Base: <?= $item['base_id'] ?></div>
                                <?php endif; ?>
                                
                                <button class="btn-config <?= $isOrphan ? 'orange' : '' ?>" onclick="toggleDetails('<?= $uniqueId ?>')">⚙️ Configurações</button>
                            </div>
                            
                            <div id="details-<?= $uniqueId ?>" class="details-panel" <?= $isOrphan ? 'style="border-color: #f39c12;"' : '' ?>>
                                <div class="editor-container">
                                    <div class="editor-form">
                                        <form id="form-item-<?= $uniqueId ?>" onsubmit="<?= $isOrphan ? 'saveBaseItemAjax' : 'saveItemAjax' ?>(event, <?= $uniqueId ?>)">
                                            <input type="hidden" name="<?= $isOrphan ? 'base_id' : 'catalog_id' ?>" value="<?= $uniqueId ?>">
                                            <?php if(!$isOrphan): ?><input type="hidden" name="base_id" value="<?= $item['base_id'] ?>"><?php endif; ?>
                                            
                                            <div class="details-grid">
                                                <?php if(!$isOrphan): ?>
                                                <div class="section-title">📦 Loja (catalog_items)</div>
                                                <div><span>Nome Catálogo</span><input type="text" name="catalog_name" value="<?= htmlspecialchars($item['catalog_name']) ?>"></div>
                                                <div><span>Créditos</span><input type="number" name="cost_credits" value="<?= $item['cost_credits'] ?>"></div>
                                                <div><span>Pontos</span><input type="number" name="cost_points" value="<?= $item['cost_points'] ?>"></div>
                                                <div><span>Tipo Pts</span><select name="points_type"><option value="0" <?= $item['points_type']==0?'selected':'' ?>>Duckets</option><option value="5" <?= $item['points_type']==5?'selected':'' ?>>Diamantes</option></select></div>
                                                <div><span>Qtd</span><input type="number" name="amount" value="<?= $item['amount'] ?>"></div>
                                                <?php endif; ?>
                                                
                                                <div class="section-title">⚙️ Físico (items_base)</div>
                                                <div><span>public_name</span><input type="text" name="public_name" value="<?= htmlspecialchars($item['public_name']) ?>"></div>
                                                <div><span>sprite_id</span><input type="number" name="sprite_id" value="<?= $item['sprite_id'] ?>"></div>
                                                <div><span>Largura (W)</span><input type="number" id="w-<?= $uniqueId ?>" name="width" value="<?= $item['width'] ?>" oninput="updateSim(<?= $uniqueId ?>)"></div>
                                                <div><span>Compr (L)</span><input type="number" id="l-<?= $uniqueId ?>" name="length" value="<?= $item['length'] ?>" oninput="updateSim(<?= $uniqueId ?>)"></div>
                                                <div><span>Altura (Z)</span><input type="text" id="z-<?= $uniqueId ?>" name="stack_height" value="<?= $item['stack_height'] ?>" oninput="updateSim(<?= $uniqueId ?>)"></div>
                                                
                                                <div class="section-title">🕹️ Interações</div>
                                                <div><span>walk</span><select id="walk-<?= $uniqueId ?>" name="allow_walk" onchange="updateSim(<?= $uniqueId ?>)"><option value="1" <?= $item['allow_walk']==1?'selected':'' ?>>SIM</option><option value="0" <?= $item['allow_walk']==0?'selected':'' ?>>NÃO</option></select></div>
                                                <div><span>sit</span><select id="sit-<?= $uniqueId ?>" name="allow_sit" onchange="updateSim(<?= $uniqueId ?>)"><option value="1" <?= $item['allow_sit']==1?'selected':'' ?>>SIM</option><option value="0" <?= $item['allow_sit']==0?'selected':'' ?>>NÃO</option></select></div>
                                                <div><span>lay</span><select id="lay-<?= $uniqueId ?>" name="allow_lay" onchange="updateSim(<?= $uniqueId ?>)"><option value="1" <?= $item['allow_lay']==1?'selected':'' ?>>SIM</option><option value="0" <?= $item['allow_lay']==0?'selected':'' ?>>NÃO</option></select></div>
                                                <div><span>stack</span><select name="allow_stack"><option value="1" <?= $item['allow_stack']==1?'selected':'' ?>>SIM</option><option value="0" <?= $item['allow_stack']==0?'selected':'' ?>>NÃO</option></select></div>
                                                <div><span>interaction</span><input type="text" name="interaction_type" value="<?= htmlspecialchars($item['interaction_type']) ?>"></div>
                                                <div><span>vending_ids</span><input type="text" name="vending_ids" value="<?= htmlspecialchars($item['vending_ids']) ?>"></div>
                                            </div>
                                            <button type="submit" class="btn-save" id="btn-save-<?= $uniqueId ?>" <?= $isOrphan ? 'style="background:#d35400;"' : '' ?>>💾 Salvar Modificações</button>
                                        </form>
                                    </div>

                                    <div class="editor-preview">
                                        <div class="room-floor"></div>
                                        <img src="https://cdn.comprahabbo.com/swf/dcr/hof_furni/<?= htmlspecialchars($item['item_name']) ?>_icon.png" class="sim-mobi" id="sim-mobi-<?= $uniqueId ?>" onerror="this.src='https://cdn.comprahabbo.com/swf/c_images/catalogue/icon_1.png'">
                                        <div class="sim-avatar" id="sim-avatar-<?= $uniqueId ?>"></div>
                                        <div style="position: absolute; bottom: 5px; left: 5px; background: rgba(0,0,0,0.5); padding: 5px; border-radius: 3px; font-size: 9px;">
                                            <button onclick="testSim(<?= $uniqueId ?>, 'stand')" style="cursor:pointer; background:#222; color:#fff; border:1px solid #555; border-radius:2px; padding:2px;">Ficar de Pé</button>
                                            <button onclick="testSim(<?= $uniqueId ?>, 'interact')" style="cursor:pointer; background:#2980b9; color:#fff; border:1px solid #555; border-radius:2px; padding:2px;">Testar Mobi</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// =======================================================
// LÓGICA DE CLIQUE ÚNICO vs CLIQUE DUPLO NAS ABAS
// =======================================================
let clickTimeout = null;

function handleTabClick(event, url, id, linkElement) {
    // Ignora completamente se clicar no input de edição, na setinha de expandir menu
    if (event.target.tagName === 'INPUT' || event.target.classList.contains('toggle-btn')) {
        return;
    }

    // Impede o link de abrir a página na mesma hora
    event.preventDefault(); 

    let span = linkElement.querySelector('.editable-name');

    if (clickTimeout !== null) {
        // --- É UM CLIQUE DUPLO ---
        clearTimeout(clickTimeout);
        clickTimeout = null;
        editInlineName(event, span, id);
    } else {
        // --- É O PRIMEIRO CLIQUE ---
        // Aguarda 250 milissegundos para ver se vem o segundo clique. Se não vier, navega.
        clickTimeout = setTimeout(function() {
            clickTimeout = null;
            window.location.href = url; 
        }, 250); 
    }
}

// =======================================================
// EDIÇÃO INLINE (Click-to-Edit)
// =======================================================
function editInlineName(e, span, id) {
    if (span.querySelector('input')) return; // Já está editando

    let currentText = span.innerText.trim();
    let input = document.createElement('input');
    input.type = 'text';
    input.value = currentText;
    input.className = 'inline-edit-input';
    input.style.width = Math.max(60, currentText.length * 8) + 'px';

    span.innerHTML = '';
    span.appendChild(input);
    input.focus();
    input.select();

    // Impede que as interações com o Input afetem a navegação
    input.addEventListener('click', function(ev) { ev.stopPropagation(); ev.preventDefault(); });
    input.addEventListener('mousedown', function(ev) { ev.stopPropagation(); });

    let isSaved = false;

    function save() {
        if(isSaved) return;
        isSaved = true;

        let newText = input.value.trim();
        if (newText === '' || newText === currentText) {
            span.innerText = currentText;
            return;
        }

        span.innerHTML = '⏳...';

        let fd = new FormData();
        fd.append('ajax_action', 'rename_page');
        fd.append('page_id', id);
        fd.append('caption', newText);

        fetch('index.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                span.innerText = d.new_caption;
                // Atualiza também o título grande se estivermos dentro desta página
                let pageTitle = document.querySelector('#page-text-view h4');
                let inputEdit = document.querySelector('input[name="caption"]');
                if (pageTitle && window.location.search.includes('page=' + id)) {
                    pageTitle.innerText = d.new_caption;
                    if(inputEdit) inputEdit.value = d.new_caption;
                }
            } else {
                span.innerText = currentText;
            }
        }).catch(() => {
            span.innerText = currentText;
        });
    }

    input.addEventListener('blur', save);
    input.addEventListener('keydown', function(ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            save();
        } else if (ev.key === 'Escape') {
            isSaved = true;
            span.innerText = currentText;
        }
    });
}

// =======================================================
// CONTROLE DOS MENUS EXPANSÍVEIS
// =======================================================
function toggleMenu(e, targetId, btn) {
    e.preventDefault();
    e.stopPropagation(); 
    
    var el = document.getElementById(targetId);
    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
        btn.innerText = '▼';
    } else {
        el.style.display = 'none';
        btn.innerText = '▶';
    }
}

function toggleDetails(id) {
    var panel = document.getElementById('details-' + id);
    var card = document.getElementById('card-' + id);
    if (panel.style.display === 'block') {
        panel.style.display = 'none';
        card.classList.remove('has-details');
    } else {
        document.querySelectorAll('.details-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.item-card').forEach(c => c.classList.remove('has-details'));
        panel.style.display = 'block';
        card.classList.add('has-details');
        updateSim(id);
    }
}

function updateSim(id) {
    let z = parseFloat(document.getElementById('z-' + id).value) || 0;
    let sit = document.getElementById('sit-' + id).value;
    let lay = document.getElementById('lay-' + id).value;
    let walk = document.getElementById('walk-' + id).value;
    
    let avatar = document.getElementById('sim-avatar-' + id);
    let mobi = document.getElementById('sim-mobi-' + id);

    let elevacaoPixels = z * 32;
    avatar.style.bottom = `calc(50% + ${elevacaoPixels}px)`;
    
    if(walk == "0" && sit == "0" && lay == "0") {
        avatar.style.bottom = `calc(50% + 15px)`;
        avatar.style.zIndex = "1";
        mobi.style.zIndex = "2";
    } else {
        avatar.style.zIndex = "3";
    }
}

function testSim(id, action) {
    let avatar = document.getElementById('sim-avatar-' + id);
    let sit = document.getElementById('sit-' + id).value;
    let lay = document.getElementById('lay-' + id).value;
    
    avatar.classList.remove('sit');
    avatar.classList.remove('lay');
    
    if (action === 'interact') {
        if (sit === "1") avatar.classList.add('sit');
        else if (lay === "1") avatar.classList.add('lay');
        else alert("Este mobi não permite sentar nem deitar nas configurações atuais.");
    }
}

function previewHeadline() {
    var select = document.getElementById('select_headline');
    var img = document.getElementById('current-headline-img');
    
    if (select && select.value !== "") {
        img.style.display = "block";
        img.onerror = function() {
            if (this.src.indexOf('.png') !== -1) {
                this.src = this.src.replace('.png', '.gif');
            } else {
                this.style.display = 'none';
            }
        };
        img.src = "/CATA/catalogue/" + select.value + ".png";
    } else if (img) {
        img.style.display = "none";
        img.src = "";
    }
}

function togglePageEdit() {
    var view = document.getElementById('page-text-view');
    var edit = document.getElementById('page-text-edit');
    var imgContainer = document.getElementById('header-img-container');
    
    if (view.style.display === 'none') {
        view.style.display = 'block';
        edit.style.display = 'none';
        imgContainer.style.display = 'block';
    } else {
        view.style.display = 'none';
        edit.style.display = 'flex';
        imgContainer.style.display = 'none'; 
    }
}

function saveItemAjax(event, id) {
    event.preventDefault();
    const btn = document.getElementById('btn-save-' + id);
    btn.innerText = '⏳ Salvando...';
    fetch('index.php', { method: 'POST', body: new FormData(event.target) }).then(r=>r.json()).then(d=>{
        if(d.success) { btn.innerText='✔️ Salvo!'; btn.style.background='#27ae60'; setTimeout(()=>btn.innerText='💾 Salvar Modificações', 2000); }
    });
}
function saveBaseItemAjax(event, id) {
    event.preventDefault();
    const btn = document.getElementById('btn-save-' + id);
    btn.innerText = '⏳ Salvando...';
    let fd = new FormData(event.target); fd.append('ajax_action', 'edit_base_only');
    fetch('index.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
        if(d.success) { btn.innerText='✔️ Salvo!'; btn.style.background='#27ae60'; setTimeout(()=>btn.innerText='💾 Salvar Base do Mobi', 2000); }
    });
}
function savePageAjax(event) {
    event.preventDefault();
    const btn = document.getElementById('btn-save-page');
    btn.innerText = '⏳ Salvando...';
    let fd = new FormData(event.target); fd.append('ajax_action', 'edit_page');
    fetch('index.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
        if(d.success) { btn.innerText='✔️ Salva!'; setTimeout(()=>location.reload(), 500); }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var tabsContainer = document.getElementById('sortable-tabs');
    if (tabsContainer) {
        new Sortable(tabsContainer, {
            animation: 150,
            filter: '.ignore-drag, .inline-edit-input',
            preventOnFilter: false,
            draggable: '.draggable-tab',
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                let fd = new FormData();
                fd.append('ajax_action', 'reorder_pages');
                
                document.querySelectorAll('#sortable-tabs .draggable-tab').forEach(tab => {
                    fd.append('page_ids[]', tab.getAttribute('data-id'));
                });

                fetch('index.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.success) console.log('Ordem das abas do topo atualizada!');
                });
            }
        });
    }

    var subpagesContainers = document.querySelectorAll('.sortable-level');
    subpagesContainers.forEach(function(container) {
        new Sortable(container, {
            animation: 150,
            draggable: '.menu-item-container',
            filter: '.toggle-btn, .inline-edit-input', 
            preventOnFilter: false, 
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                let fd = new FormData();
                fd.append('ajax_action', 'reorder_pages');
                
                let items = Array.from(evt.to.children).filter(el => el.classList.contains('menu-item-container'));
                items.forEach(sub => {
                    fd.append('page_ids[]', sub.getAttribute('data-id'));
                });

                fetch('index.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.success) console.log('Ordem das sub-páginas atualizada com sucesso!');
                });
            }
        });
    });
});
</script>

</body>
</html>
