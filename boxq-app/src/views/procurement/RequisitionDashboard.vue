<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Item {
    name: string;
    qty: number;
    price: number;
}

interface Requisition {
    _id: string;
    id: number;
    requester: string;
    department: string;
    items: Item[];
    total_price: number;
    currency: string;
    status: string;
    created_at: string;
}

interface PaginationData {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

const router = useRouter();
const toast = useToast();

const requisitions = ref<Requisition[]>([]);
const loading = ref(true);

const searchQuery = ref('');
const statusFilter = ref('');
const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15
});

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const fetchRequisitions = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/requisitions', {
            params: {
                page: page,
                search: searchQuery.value,
                status: statusFilter.value
            }
        });
        
        requisitions.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total,
            per_page: response.data.per_page
        };
    } catch (error) {
        toast.error("Failed to load requisitions.");
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchRequisitions();
});

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchRequisitions(1);
    }, 500);
});

const onStatusChange = () => {
    fetchRequisitions(1);
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        fetchRequisitions(page);
    }
};

const viewDetails = (id: string | number) => {
    router.push(`/requisition/${id}`);
};

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        'Approved': 'bg-success',
        'Draft': 'bg-secondary',
        'Pending': 'bg-warning text-dark',
        'Rejected': 'bg-danger',
        'PO Created': 'bg-info text-dark',
        'Received': 'bg-primary',
        'Processing Payment': 'bg-secondary text-white',
        'Paid': 'bg-primary text-white',
        'Reconciled': 'bg-secondary text-white'
    };
    return map[status] || 'bg-light text-dark border';
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatId = (id: string | number) => {
    const str = String(id);
    return '#' + str.slice(-6).toUpperCase();
};
</script>

<script lang="ts"> export default { name: 'RequisitionDashboard' } </script>

<template>
    <MainLayout>
        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1">Overview</h3>
            <p class="text-muted mb-0">Track and manage recent requests.</p>
        </div>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="input-group" style="max-width: 350px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input v-model="searchQuery" type="text" class="form-control border-start-0 bg-light" placeholder="Search by name, dept, or details...">
                </div>
                
                <select v-model="statusFilter" @change="onStatusChange" class="form-select bg-light" style="max-width: 200px;">
                    <option value="">All Statuses</option>
                    <option value="Draft">Draft</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="PO Created">PO Created</option>
                    <option value="Processing Payment">Processing Payment</option>
                    <option value="Paid">Paid</option>
                    <option value="Reconciled">Reconciled</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="py-3 px-4 border-0">ID</th>
                            <th class="py-3 border-0">Requester</th>
                            <th class="py-3 border-0">Department</th>
                            <th class="py-3 border-0 text-center">Items</th>
                            <th class="py-3 border-0">Total</th>
                            <th class="py-3 border-0">Status</th>
                            <th class="py-3 border-0">Date</th>
                            <th class="py-3 px-4 border-0 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody v-if="loading">
                        <tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>
                    </tbody>
                    <tbody v-else-if="requisitions.length === 0">
                        <tr><td colspan="8" class="text-center py-5 text-muted">No requests found matching your criteria.</td></tr>
                    </tbody>
                    <tbody v-else class="border-top-0">
                        <tr v-for="req in requisitions" :key="req._id || req.id" class="border-bottom">
                            <td class="px-4 py-3"><span class="text-primary fw-bold">{{ formatId(req._id || req.id) }}</span></td>
                            <td class="py-3 fw-bold text-dark">{{ req.requester }}</td>
                            <td class="py-3"><span class="badge border text-dark bg-white shadow-sm">{{ req.department }}</span></td>
                            <td class="py-3 text-center text-muted">{{ req.items ? req.items.length : 0 }}</td>
                            <td class="py-3 fw-bold text-dark">{{ req.currency === 'USD' ? '$' : 'Rp' }}{{ Number(req.total_price).toLocaleString() }}</td>
                            <td class="py-3"><span class="badge rounded-pill px-3 py-2 fw-medium" :class="getStatusBadge(req.status)">{{ req.status }}</span></td>
                            <td class="py-3 text-muted small">{{ formatDate(req.created_at) }}</td>
                            <td class="px-4 py-3 text-end">
                                <button v-if="req.status === 'Approved'" @click="viewDetails(req._id || req.id)" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm">Process</button>
                                <button v-else @click="viewDetails(req._id || req.id)" class="btn btn-light border btn-sm px-3 shadow-sm text-dark">View</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="!loading && pagination.last_page > 1" class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Showing page <strong>{{ pagination.current_page }}</strong> of <strong>{{ pagination.last_page }}</strong> ({{ pagination.total }} total records)</span>
                <div class="btn-group shadow-sm">
                    <button @click="goToPage(pagination.current_page - 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === 1"><i class="fa-solid fa-chevron-left"></i></button>
                    <button @click="goToPage(pagination.current_page + 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === pagination.last_page"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>