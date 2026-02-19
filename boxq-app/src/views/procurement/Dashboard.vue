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
    id: string;
    requester: string;
    department: string;
    items: Item[];
    total_price: number;
    status: string;
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
        console.error(error);
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

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const user = JSON.parse(userData);
        userRole.value = user.role;
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
            <RouterLink v-if="userRole === 'employee' || userRole === 'admin'" to="/create" class="btn btn-dark shadow-sm">
                <i class="fa-solid fa-plus me-2"></i>New Requisition
            </RouterLink>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <div v-else-if="requisitions.length === 0" class="text-center py-5 bg-white rounded shadow-sm border">
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
                        <tr v-for="req in requisitions" :key="req.id">
                            <td class="ps-4 font-monospace text-muted small">
                                #{{ String(req.id).slice(-6).toUpperCase() }}
                            </td>
                            <td class="fw-bold text-dark">{{ req.requester }}</td>
                            <td><span class="badge bg-light text-dark border">{{ req.department }}</span></td>
                            <td>{{ req.items.length }}</td>
                            <td class="fw-bold">${{ Number(req.total_price).toFixed(2) }}</td>
                            <td>
                                <span v-if="req.status === 'Pending'" class="badge bg-warning text-dark bg-opacity-75">Pending</span>
                                <span v-else-if="req.status === 'Approved'" class="badge bg-success">Approved</span>
                                <span v-else-if="req.status === 'Rejected'" class="badge bg-danger">Rejected</span>
                                <span v-else-if="req.status === 'Paid'" class="badge bg-primary">Paid</span>
                                <span v-else class="badge bg-secondary">{{ req.status }}</span>
                            </td>
                            <td class="text-muted small">
                                {{ new Date(req.created_at).toLocaleDateString() }}
                            </td>
                            <td v-if="userRole !== 'employee'" class="text-end pe-4">
                                <div v-if="userRole === 'manager' && req.status === 'Pending'">
                                    <button @click="updateStatus(req.id, 'Approved')" class="btn btn-sm btn-outline-success me-2">Approve</button>
                                    <button @click="updateStatus(req.id, 'Rejected')" class="btn btn-sm btn-outline-danger">Reject</button>
                                </div>
                                <div v-else-if="userRole === 'finance' && req.status === 'Approved'">
                                    <button @click="updateStatus(req.id, 'Paid')" class="btn btn-sm btn-primary">Process Payment</button>
                                </div>
                                <div v-else-if="userRole === 'admin' && req.status !== 'Paid'">
                                    <button @click="updateStatus(req.id, 'Approved')" class="btn btn-sm btn-outline-secondary">Force Approve</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>