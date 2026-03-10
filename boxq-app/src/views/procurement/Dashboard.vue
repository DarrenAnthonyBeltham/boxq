<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Item {
    name: string;
    price: number;
    qty: number;
}

interface Requisition {
    id?: string;
    _id?: string | { $oid: string };
    requester: string;
    department: string;
    justification?: string;
    items?: Item[];
    total_price: number;
    status: string;
    reason?: string;
    created_at: string;
}

const requisitions = ref<Requisition[]>([]);
const loading = ref(true);
const userRole = ref('');

const fetchRequisitions = async () => {
    try {
        const response = await api.get('/requisitions');
        requisitions.value = response.data;
    } catch (error) {
        console.error("Error fetching requisitions:", error);
    } finally {
        loading.value = false;
    }
};

const updateStatus = async (id: string, newStatus: string) => {
    try {
        await api.patch(`/requisitions/${id}/status`, { status: newStatus });
        await fetchRequisitions();
    } catch (error) {
        alert("Failed to update status.");
    }
};

const getSafeId = (req: Requisition) => {
    if (req.id) return String(req.id);
    if (req._id) {
        return typeof req._id === 'object' ? String(req._id.$oid) : String(req._id);
    }
    return 'UNKNOWN';
};

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const user = JSON.parse(userData);
        userRole.value = user.role || '';
    }
    fetchRequisitions();
});
</script>

<script lang="ts">
export default {
    name: 'DashboardView'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Overview</h3>
                <p class="text-muted mb-0">Track and manage recent requests.</p>
            </div>
            <RouterLink v-if="userRole !== 'finance'" to="/create" class="btn btn-dark shadow-sm">
                <i class="fa-solid fa-plus me-2"></i>New Requisition
            </RouterLink>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        <div v-else-if="!requisitions || requisitions.length === 0" class="text-center py-5 bg-white rounded shadow-sm border">
            <i class="fa-regular fa-folder-open fa-3x text-light mb-3"></i>
            <h5 class="text-muted">No requisitions found</h5>
            <p class="text-secondary small">Start by creating a new request.</p>
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-uppercase small fw-bold text-secondary">ID</th>
                            <th class="text-uppercase small fw-bold text-secondary">Requester</th>
                            <th class="text-uppercase small fw-bold text-secondary">Department</th>
                            <th class="text-uppercase small fw-bold text-secondary">Items</th>
                            <th class="text-uppercase small fw-bold text-secondary">Total</th>
                            <th class="text-uppercase small fw-bold text-secondary">Status</th>
                            <th class="text-uppercase small fw-bold text-secondary">Date</th>
                            <th v-if="userRole !== 'employee'" class="text-uppercase small fw-bold text-secondary text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="req in requisitions" :key="getSafeId(req)">
                            <td class="ps-4 font-monospace small">
                                <RouterLink :to="`/requisition/${getSafeId(req)}`" class="text-decoration-none fw-bold">
                                    #{{ getSafeId(req).slice(-6).toUpperCase() }}
                                </RouterLink>
                            </td>
                            <td class="fw-bold text-dark">{{ req.requester || 'Unknown' }}</td>
                            <td><span class="badge bg-light text-dark border">{{ req.department || 'N/A' }}</span></td>
                            <td>{{ req.items ? req.items.length : 0 }}</td>
                            <td class="fw-bold">${{ Number(req.total_price || 0).toFixed(2) }}</td>
                            <td>
                                <span v-if="req.status === 'Pending'" class="badge bg-warning text-dark bg-opacity-75">Pending</span>
                                <span v-else-if="req.status === 'Approved'" class="badge bg-success">Approved</span>
                                <span v-else-if="req.status === 'Rejected'" class="badge bg-danger">Rejected</span>
                                <span v-else-if="req.status === 'Paid'" class="badge bg-primary">Paid</span>
                                <span v-else class="badge bg-secondary">{{ req.status || 'Unknown' }}</span>
                            </td>
                            <td class="text-muted small">
                                {{ req.created_at ? new Date(req.created_at).toLocaleDateString() : 'N/A' }}
                            </td>
                            <td v-if="userRole !== 'employee'" class="text-end pe-4">
                                <div v-if="userRole === 'manager' && req.status === 'Pending'">
                                    <RouterLink :to="`/requisition/${getSafeId(req)}`" class="btn btn-sm btn-primary px-3">
                                        Review Request
                                    </RouterLink>
                                </div>
                                <div v-else-if="userRole === 'finance' && req.status === 'Approved'">
                                    <RouterLink :to="`/requisition/${getSafeId(req)}`" class="btn btn-sm btn-primary px-3">
                                        Process Payment
                                    </RouterLink>
                                </div>
                                <div v-else-if="userRole === 'admin' && req.status !== 'Paid'">
                                    <button @click="updateStatus(getSafeId(req), 'Approved')" class="btn btn-sm btn-outline-secondary">Force Approve</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>