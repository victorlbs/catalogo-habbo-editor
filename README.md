# 🏨 Habbo Catalog Editor PRO

[![Habbo](https://img.shields.io/badge/Habbo-Retro-orange.svg)](https://github.com/victorlbs/catalogo-habbo-editor)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777bb4.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479a1.svg)](https://www.mysql.com/)

Um gerenciador de catálogo avançado para servidores Habbo (Arcturus, Morningstar e derivados), desenvolvido em PHP e JavaScript para oferecer uma experiência de edição **visual, intuitiva e em tempo real**. Chega de sofrer editando tabelas gigantes no Navicat!

---

## 🚀 Funcionalidades Principais

* **🌳 Estrutura em Árvore Recursiva:** Navegação idêntica ao catálogo oficial, suportando infinitos níveis de subpáginas.
* **⚡ Edição Real-Time (AJAX):** Altere preços, nomes e configurações físicas dos mobis sem precisar recarregar a página.
* **📐 Simulador Isométrico de Testes:** Visualize como o mobi se comporta no quarto antes mesmo de abrir o jogo. Teste altura (`stack_height`), largura, e as funções de sentar (🪑), deitar (🛏️) e andar (👣).
* **📦 Detector de Mobis "Órfãos":** Encontre automaticamente mobis que existem na sua `items_base` mas ainda não foram colocados à venda no catálogo.
* **🖼️ Preview de Assets:** Integração com CDN para exibição automática de ícones de mobis e banners (`headlines`) de categorias.
* **🛠️ Editor de Páginas Completo:** Mude layouts, ícones, ordens e textos informativos das abas com interface amigável.

---

## 🛠️ Instalação e Configuração

### 1. Requisitos
* Servidor Web (XAMPP, WAMP, IIS ou VPS Linux).
* PHP 7.4 ou superior.
* Banco de dados de um emulador Habbo (Arcturus Emulator ou Morningstar).

### 2. Configuração do Banco de Dados
Edite o arquivo `conexao.php` com as credenciais do seu MySQL:

```php
$host = 'localhost';
$db   = 'sua_database';
$user = 'root';
$pass = 'sua_senha';
