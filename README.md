# Inventory Admin

Sistema de gestão de inventário e stock, desenvolvido com Laravel, para controlar
produtos, compras, vendas e fornecedores num único painel administrativo.

## Funcionalidades

- **Autenticação** com JWT, limite de tentativas de login e bloqueio de utilizadores inativos
- **Perfis de acesso**: administrador e operador, com permissões distintas
- **Produtos e categorias**: cadastro, preços e histórico de preços
- **Fornecedores**: gestão completa de fornecedores
- **Compras**: registo, confirmação e cancelamento, com entrada automática em stock
- **Vendas**: registo e cancelamento, com saída automática de stock
- **Controlo de stock**: saldo por produto e histórico de movimentações
- **Dashboard**: resumo geral, vendas por dia, produtos mais vendidos e alertas de stock baixo
- **Notificações** para o utilizador
- **Exportação** da lista de produtos em Excel e PDF
- **Gestão de utilizadores** e perfil (restrito ao administrador)

## Tecnologias

- PHP 8.3+ e Laravel 13
- JWT (`tymon/jwt-auth`) e Laravel Sanctum
- Laravel Excel (`maatwebsite/excel`) e DomPDF (`barryvdh/laravel-dompdf`)
- Tailwind CSS 4 e Vite
- SQLite (configurável para outros bancos)

## Instalação

    git clone https://github.com/SEU_USUARIO/inventory-admin.git
    cd inventory-admin
    composer setup
    php artisan jwt:secret
    composer dev

O comando `composer setup` instala as dependências, cria o `.env`, gera a chave da
aplicação, executa as migrations e compila os assets.
