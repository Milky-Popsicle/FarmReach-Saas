@extends('layouts.app')

@section('title', 'Admin Dashboard - FarmReach Cloud')

@section('content')
<div class="container-fluid py-4 px-md-5">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-1">
                Admin View
            </span>
            <h2 class="fw-bold mb-0 text-farm-primary">Administrator Control Panel</h2>
            <p class="text-muted small mb-0">System performance, trade analytics, user activity, and announcements</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i> Manage Users
            </a>
            <a href="{{ route('admin.announcements') }}" class="btn btn-farm-primary btn-sm">
                <i class="bi bi-megaphone me-1"></i> Post Announcement
            </a>
            <a href="{{ route('admin.recipes') }}" class="btn btn-farm-outline btn-sm">
                <i class="bi bi-book me-1"></i> Recipe Directory
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards (Wireframe page 24: Total Farmers, Total Buyers, Total Sales, Pending Orders) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">Total Farmers</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">{{ $totalFarmers }}</h3>
                <small class="text-muted"><i class="bi bi-tree text-success"></i> Registered growers</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-secondary">
                <span class="text-muted small text-uppercase fw-semibold">Total Buyers</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">{{ $totalBuyers }}</h3>
                <small class="text-muted"><i class="bi bi-people text-primary"></i> Registered consumers</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-accent">
                <span class="text-muted small text-uppercase fw-semibold">Total Sales</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">₱{{ number_format($totalSales, 2) }}</h3>
                <small class="text-muted"><i class="bi bi-graph-up-arrow text-success"></i> Completed orders</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">Pending Orders</span>
                <h3 class="fw-bold mb-0 text-warning mt-1">{{ $pendingOrders }}</h3>
                <small class="text-muted"><i class="bi bi-clock-history text-warning"></i> Awaiting confirmation</small>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Sales Overview Chart (Wireframe page 24: Sales Overview) -->
        <div class="col-lg-7">
            <div class="farm-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-farm-primary mb-0">
                        <i class="bi bi-graph-up text-success me-2"></i>Sales Overview (Last 30 Days)
                    </h5>
                    <span class="badge bg-light text-muted border">Daily Volume (₱)</span>
                </div>
                <div style="height: 280px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Products Sold (Wireframe page 24: Top Products Sold) -->
        <div class="col-lg-5">
            <div class="farm-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-farm-primary mb-0">
                        <i class="bi bi-trophy text-warning me-2"></i>Top Products Sold
                    </h5>
                    <span class="badge bg-light text-muted border">Ranked</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Crop</th>
                                <th>Farmer</th>
                                <th>Volume Sold</th>
                                <th>Total (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $top)
                                <tr>
                                    <td class="fw-bold text-dark">{{ $top->product->name ?? 'Produce' }}</td>
                                    <td>{{ $top->product->farmer->name ?? 'N/A' }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $top->total_qty_sold }} units</span></td>
                                    <td class="fw-bold text-success">₱{{ number_format($top->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">No sales recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Announcements (Wireframe page 24: Announcements) -->
        <div class="col-lg-6">
            <div class="farm-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-farm-primary mb-0">
                        <i class="bi bi-megaphone text-warning me-2"></i>Recent Announcements
                    </h5>
                    <a href="{{ route('admin.announcements') }}" class="btn btn-sm btn-farm-primary py-0 px-2">+ Add New</a>
                </div>

                <div class="list-group list-group-flush">
                    @forelse($recentAnnouncements as $ann)
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-dark">{{ $ann->title }}</h6>
                                <small class="text-muted">{{ $ann->event_date ?? $ann->created_at->format('M d, Y') }}</small>
                            </div>
                            <small class="text-muted d-block text-truncate">{{ $ann->description }}</small>
                        </div>
                    @empty
                        <p class="text-muted small py-3">No announcements posted yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Activity Logs (Wireframe page 24: Recent Activities) -->
        <div class="col-lg-6">
            <div class="farm-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-farm-primary mb-0">
                        <i class="bi bi-activity text-danger me-2"></i>Recent Activity Logs
                    </h5>
                    <span class="badge bg-light text-muted border">System Audit</span>
                </div>

                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Details</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivities as $log)
                                <tr>
                                    <td>
                                        <span class="badge bg-farm-primary small">{{ $log->action }}</span>
                                    </td>
                                    <td class="text-muted text-truncate" style="max-width: 200px;" title="{{ $log->description }}">
                                        {{ $log->description }}
                                    </td>
                                    <td class="text-muted" style="white-space: nowrap;">
                                        {{ $log->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No activity logs recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rawSales = @json($salesData);
        const labels = rawSales.length ? rawSales.map(item => item.order_date) : ['Today'];
        const data = rawSales.length ? rawSales.map(item => parseFloat(item.daily_total)) : [0];

        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: data,
                    borderColor: '#1B4332',
                    backgroundColor: 'rgba(27, 67, 50, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#DAA520',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
</script>
@endpush
@endsection
