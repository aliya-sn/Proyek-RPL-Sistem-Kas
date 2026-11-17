    <!-- Footer -->
    <footer class="footer-app">
        <div class="container-fluid">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <span class="text-muted small">
                    &copy; <?= date('Y') ?> <?= e(config('app.name', 'Sistem Keuangan Kas')) ?>. All rights reserved.
                </span>
                <span class="text-muted small">
                    <i class="bi bi-code-slash me-1"></i>v1.0.0 &mdash; Built with <i class="bi bi-heart-fill text-danger"></i> &amp; AI
                </span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom App JS -->
    <script src="<?= asset('js/app.js') ?>"></script>

    <script>
        // Initialize Dashboard Chart if element and data exists
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('financeChart');
            if (ctx && window.dashboardChartData) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: window.dashboardChartData.labels,
                        datasets: [
                            {
                                label: 'Pemasukan (Rp)',
                                data: window.dashboardChartData.masuk,
                                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                                borderColor: 'rgb(25, 135, 84)',
                                borderWidth: 1
                            },
                            {
                                label: 'Pengeluaran (Rp)',
                                data: window.dashboardChartData.keluar,
                                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                                borderColor: 'rgb(220, 53, 69)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
