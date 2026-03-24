<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, LineElement, PointElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, LineElement, PointElement);

interface Anomaly {
    type: string;
    description: string;
    requests?: string[];
}

interface DashboardStats {
    total_spent_this_month_usd: number;
    projected_total_month_spend: number;
    trend_labels: string[];
    actual_trend: (number | null)[];
    projected_trend: (number | null)[];
    spending_by_department: Record<string, number>;
    vendor_analysis: Record<string, number>;
    top_items: Record<string, number>;
    avg_cycle_time_days: number;
    anomalies: Anomaly[];
}

const toast = useToast();
const loading = ref(true);
const isExporting = ref(false);

const stats = ref<DashboardStats>({
    total_spent_this_month_usd: 0,
    projected_total_month_spend: 0,
    trend_labels: [],
    actual_trend: [],
    projected_trend: [],
    spending_by_department: {},
    vendor_analysis: {},
    top_items: {},
    avg_cycle_time_days: 0,
    anomalies: []
});

const lineChartData = ref({
    labels: [] as string[],
    datasets: [
        { label: 'Actual Spend (USD)', borderColor: '#0d6efd', backgroundColor: '#0d6efd', data: [] as (number | null)[], tension: 0.3 },
        { label: 'Projected Spend (USD)', borderColor: '#6c757d', backgroundColor: '#6c757d', borderDash: [5, 5], data: [] as (number | null)[], tension: 0.3 }
    ]
});

const barChartData = ref({
    labels: [] as string[],
    datasets: [{ label: 'Spend (USD)', backgroundColor: '#0d6efd', data: [] as number[] }]
});

const doughnutChartData = ref({
    labels: [] as string[],
    datasets: [{ backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0'], data: [] as number[] }]
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' as const } }
};

onMounted(async () => {
    try {
        const response = await api.get('/analytics/dashboard');
        stats.value = response.data;

        lineChartData.value.labels = stats.value.trend_labels || [];
        if (lineChartData.value.datasets[0] && lineChartData.value.datasets[1]) {
            lineChartData.value.datasets[0].data = stats.value.actual_trend || [];
            lineChartData.value.datasets[1].data = stats.value.projected_trend || [];
        }

        const deptSpending = stats.value.spending_by_department || {};
        barChartData.value.labels = Object.keys(deptSpending);
        if (barChartData.value.datasets[0]) {
            barChartData.value.datasets[0].data = Object.values(deptSpending);
        }

        const topItems = stats.value.top_items || {};
        doughnutChartData.value.labels = Object.keys(topItems);
        if (doughnutChartData.value.datasets[0]) {
            doughnutChartData.value.datasets[0].data = Object.values(topItems);
        }
    } catch (error) {
        toast.error("Failed to load analytics data.");
    } finally {
        loading.value = false;
    }
});

const downloadCsv = async () => {
    isExporting.value = true;
    toast.info("Compiling financial CSV...");
    try {
        const response = await api.get('/analytics/export', { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'boxq_financial_export.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success("CSV Export Complete!");
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            toast.error(error.response?.data?.message || "Failed to export data.");
        } else {
            toast.error("Failed to export data.");
        }
    } finally {
        isExporting.value = false;
    }
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};
</script>

<script lang="ts">
export default { name: 'AnalyticsDashboardView' }
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-0">Financial Intelligence</h3>
                <p class="text-muted mb-0 small">Real-time spend analysis and anomaly detection.</p>
            </div>
            <button @click="downloadCsv" class="btn btn-dark shadow-sm fw-bold" :disabled="isExporting">
                <span v-if="isExporting" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="fa-solid fa-file-csv me-2"></i>Export Full CSV
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-primary text-white h-100">
                        <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <h6 class="text-uppercase fw-bold opacity-75 mb-1">Total MTD Spend</h6>
                            <h2 class="fw-bold mb-0 display-6">{{ formatCurrency(stats.total_spent_this_month_usd) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-dark text-white h-100">
                        <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <h6 class="text-uppercase fw-bold opacity-75 mb-1">EOM Projected Spend</h6>
                            <h2 class="fw-bold mb-0 display-6 text-info">{{ formatCurrency(stats.projected_total_month_spend) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white h-100">
                        <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
                            <h6 class="text-uppercase text-muted fw-bold mb-1">Avg Cycle Time (Req to Pay)</h6>
                            <h2 class="fw-bold text-dark mb-0 display-6">{{ stats.avg_cycle_time_days }} <span class="fs-5 text-muted">Days</span></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="stats.anomalies.length > 0" class="alert alert-danger shadow-sm border-0 mb-4">
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>System Anomalies Detected</h6>
                <ul class="mb-0 small">
                    <li v-for="(anomaly, index) in stats.anomalies" :key="index">
                        <strong>{{ anomaly.type }}:</strong> {{ anomaly.description }}
                    </li>
                </ul>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-4">Budget Burn Rate & Forecast</h6>
                    <div style="height: 350px;">
                        <Line v-if="lineChartData.labels.length" :data="lineChartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-4">Department Spend Distribution</h6>
                            <div style="height: 300px;">
                                <Bar v-if="barChartData.labels.length" :data="barChartData" :options="chartOptions" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-4">Top Spending Categories</h6>
                            <div style="height: 300px;">
                                <Doughnut v-if="doughnutChartData.labels.length" :data="doughnutChartData" :options="chartOptions" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-4">Vendor Relationship Analysis</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="small text-secondary">Vendor Name</th>
                                    <th class="small text-secondary text-end">Total Volume (USD Equivalent)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(amount, vendor) in stats.vendor_analysis" :key="vendor">
                                    <td class="fw-bold text-dark">{{ vendor }}</td>
                                    <td class="text-end fw-bold text-primary">{{ formatCurrency(amount) }}</td>
                                </tr>
                                <tr v-if="Object.keys(stats.vendor_analysis || {}).length === 0">
                                    <td colspan="2" class="text-center text-muted py-3">No paid vendor data available yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>