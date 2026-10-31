<main id="page-categories" class="container hidden">
    <x-page-head title="Categorias">
        <button id="new-category-btn" class="btn primary">+ Nova categoria</button>
    </x-page-head>

    <div class="toolbar">
        <input type="search" id="category-search" maxlength="100" autocomplete="off"
               placeholder="Buscar categoria...">
    </div>

    <x-data-table
        body="category-rows"
        :columns="['Nome', ['Produtos', 'num'], 'Criada em', '']" />
</main>

<x-modal id="category-dialog">
    <form id="category-form">
        <h2 id="category-title">Nova categoria</h2>

        <label>Nome
            <input id="c-name" required maxlength="100">
        </label>

        <ul id="c-errors" class="error hidden"></ul>

        <div class="dialog-actions">
            <button type="button" id="c-cancel" class="btn">Cancelar</button>
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</x-modal>
