<main id="page-dashboard" class="container">
    <x-page-head title="Resumo do mês">
        <input type="month" id="month">
    </x-page-head>

    <div class="cards" id="cards"></div>

    <div class="charts">
        <div class="card chart-card">
            <h3>Produtos mais vendidos</h3>
            <div class="chart-box"><canvas id="top-chart"></canvas></div>
            <p id="top-empty" class="muted hidden">Sem vendas neste mês.</p>
        </div>

        <div class="card chart-card">
            <h3>Faturamento por dia</h3>
            <div class="chart-box"><canvas id="day-chart"></canvas></div>
        </div>
    </div>
</main>
