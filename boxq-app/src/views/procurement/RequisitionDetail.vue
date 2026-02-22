<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

const route = useRoute();
const router = useRouter();

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
    items: Item[];
    total_price: number;
    status: string;
    reason?: string;
    created_at: string;
}

const req = ref<Requisition | null>(null);
const loading = ref(true);
const userRole = ref('');
const statusReason = ref('');

const fetchDetail = async () => {
    try {
        const response = await api.get(`/requisitions/${route.params.id}`);
        req.value = response.data;
    } catch (error) {
        alert("Unable to load requisition details.");
        router.push('/');
    } finally {
        loading.value = false;
    }
};

const updateStatus = async (newStatus: string) => {
    if (!req.value) return;
    
    if (newStatus === 'Rejected' && !statusReason.value.trim()) {
        alert("Please provide a reason for rejection.");
        return;
    }

    try {
        const idToUpdate = req.value.id || (typeof req.value._id === 'object' ? req.value._id.$oid : req.value._id);
        
        await api.patch(`/requisitions/${idToUpdate}/status`, { 
            status: newStatus,
            reason: statusReason.value 
        });
        statusReason.value = '';
        await fetchDetail();
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
    fetchDetail();
});
</script>

<script lang="ts">
export default {
    name: 'RequisitionDetailView'
}
</script>

<template>
    <MainLayout>
        <div class="mb-4">
            <button @click="router.push('/')" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
            </button>
            <h3 class="fw-bold text-dark mb-1">Requisition Details</h3>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        <div v-else-if="req" class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div class="font-monospace text-muted fw-bold">
                    #{{ String(req.id || (typeof req._id === 'object' ? req._id.$oid : req._id)).slice(-6).toUpperCase() }}
                </div>
                <div>
                    <span v-if="req.status === 'Pending'" class="badge bg-warning text-dark bg-opacity-75 fs-6">Pending Review</span>
                    <span v-else-if="req.status === 'Approved'" class="badge bg-success fs-6">Approved</span>
                    <span v-else-if="req.status === 'Rejected'" class="badge bg-danger fs-6">Rejected</span>
                    <span v-else-if="req.status === 'Paid'" class="badge bg-primary fs-6">Payment Processed</span>
                </div>
            </div>

            <div class="card-body p-4">
                
                <div v-if="req.reason" class="alert mb-4 d-flex gap-3" :class="req.status === 'Rejected' ? 'alert-danger' : 'alert-success'">
                    <i class="fa-solid mt-1" :class="req.status === 'Rejected' ? 'fa-circle-exclamation' : 'fa-comment-dots'"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">
                            {{ req.status === 'Rejected' ? 'Requisition Rejected' : 'Manager Notes' }}
                        </h6>
                        <p class="mb-0 small">{{ req.reason }}</p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">Requested By</h6>
                        <h5 class="fw-bold mb-1">{{ req.requester }}</h5>
                        <p class="text-secondary mb-0">{{ req.department }} Department</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-4 mt-md-0">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">Submission Date</h6>
                        <h5 class="fw-bold mb-0">{{ new Date(req.created_at).toLocaleDateString() }}</h5>
                    </div>
                </div>

                <div class="bg-light p-3 rounded border mb-5">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">Business Justification</h6>
                    <p class="mb-0 text-dark">{{ req.justification || 'No justification provided for this request.' }}</p>
                </div>

                <h6 class="text-uppercase text-muted small fw-bold mb-3">Line Items</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-secondary small">Description</th>
                                <th class="text-center text-secondary small" style="width: 100px;">Qty</th>
                                <th class="text-end text-secondary small" style="width: 150px;">Unit Price</th>
                                <th class="text-end text-secondary small" style="width: 150px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in req.items" :key="index">
                                <td class="fw-medium">{{ item.name }}</td>
                                <td class="text-center">{{ item.qty }}</td>
                                <td class="text-end">${{ Number(item.price).toFixed(2) }}</td>
                                <td class="text-end fw-bold">${{ (item.price * item.qty).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="3" class="text-end text-uppercase small fw-bold text-secondary align-middle">Grand Total</td>
                                <td class="text-end fs-5 fw-bold text-dark">${{ Number(req.total_price).toFixed(2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-if="userRole === 'manager' && req.status === 'Pending'" class="bg-light p-4 rounded border mt-4">
                    <h6 class="fw-bold mb-3">Manager Review</h6>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Manager Comments (Required for rejection, optional for approval)</label>
                        <textarea v-model="statusReason" class="form-control" rows="2" placeholder="Leave a note for the employee..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button @click="updateStatus('Rejected')" class="btn btn-outline-danger px-4">Reject</button>
                        <button @click="updateStatus('Approved')" class="btn btn-success px-4">Approve</button>
                    </div>
                </div>
                
                <div v-else-if="userRole === 'finance' && req.status === 'Approved'" class="bg-light p-3 rounded border d-flex justify-content-end mt-4">
                    <button @click="updateStatus('Paid')" class="btn btn-primary px-4">
                        <i class="fa-solid fa-check-double me-2"></i>Mark as Paid
                    </button>
                </div>

                <div v-else-if="userRole === 'admin' && req.status !== 'Paid'" class="bg-light p-3 rounded border d-flex justify-content-end mt-4">
                    <button @click="updateStatus('Approved')" class="btn btn-outline-secondary px-4">Force Approve</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>