<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface RequisitionItem {
    name: string;
    price: number;
    qty: number;
}

interface CostCenter {
    department: string;
    percentage: number;
}

interface Requisition {
    id: number;
    _id?: string;
    user_id: string;
    requester: string;
    department: string;
    justification: string;
    items: RequisitionItem[];
    total_price: number;
    currency: string;
    exchange_rate: number;
    cost_centers: CostCenter[];
    status: string;
    approval_stage?: string;
    reason?: string;
    attachment?: string;
    created_at: string;
}

const route = useRoute();
const router = useRouter();
const requisition = ref<Requisition | null>(null);
const loading = ref(true);
const currentUser = ref({ role: '', department: '' });

const isProcessing = ref(false);
const showRejectInput = ref(false);
const rejectReason = ref('');

onMounted(async () => {
    const userData = localStorage.getItem('user');
    if (userData) {
        currentUser.value = JSON.parse(userData);
    }
    await fetchRequisition();
});

const fetchRequisition = async () => {
    try {
        const response = await api.get(`/requisitions/${route.params.id}`);
        requisition.value = response.data;
    } catch (error) {
        console.error("Failed to fetch requisition:", error);
        router.push('/');
    } finally {
        loading.value = false;
    }
};

const cloneRequest = () => {
    if (requisition.value) {
        const targetId = requisition.value._id || requisition.value.id;
        router.push(`/create?clone_id=${targetId}`);
    }
};

const updateStatus = async (newStatus: string) => {
    if (newStatus === 'Rejected' && !rejectReason.value) {
        alert("Please provide a reason for rejection.");
        return;
    }

    isProcessing.value = true;
    try {
        await api.patch(`/requisitions/${route.params.id}/status`, {
            status: newStatus,
            reason: newStatus === 'Rejected' ? rejectReason.value : null
        });
        await fetchRequisition();
        showRejectInput.value = false;
        rejectReason.value = '';
    } catch (error) {
        alert("Failed to update status.");
    } finally {
        isProcessing.value = false;
    }
};

const getStatusBadge = (status: string) => {
    switch(status) {
        case 'Approved': return 'bg-success';
        case 'Rejected': return 'bg-danger';
        case 'Paid': return 'bg-info text-dark';
        default: return 'bg-warning text-dark';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};
</script>

<script lang="ts">
export default {
    name: 'RequisitionDetailView'
}
</script>

<template>
    <MainLayout>
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button @click="router.push('/')" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div>
                    <h3 class="fw-bold text-dark mb-0">Requisition Details</h3>
                    <p class="text-muted mb-0 small">Review and process the purchase request.</p>
                </div>
            </div>
            <button @click="cloneRequest" class="btn btn-outline-primary shadow-sm">
                <i class="fa-solid fa-copy me-2"></i>Clone Request
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else-if="requisition" class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h5 class="fw-bold mb-1">Request by {{ requisition.requester }}</h5>
                                <span class="text-muted small">{{ requisition.department }} Department • {{ formatDate(requisition.created_at) }}</span>
                            </div>
                            <div class="text-end">
                                <span class="badge px-3 py-2 fs-6 mb-1 d-block" :class="getStatusBadge(requisition.status)">
                                    {{ requisition.status }}
                                </span>
                                <span v-if="requisition.status === 'Pending' && requisition.approval_stage" class="small text-muted fw-bold">
                                    Awaiting: {{ requisition.approval_stage }}
                                </span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-2">Business Justification</h6>
                            <p class="mb-0 bg-light p-3 rounded text-dark">{{ requisition.justification }}</p>
                        </div>

                        <div v-if="requisition.attachment" class="mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-2">Attached Documentation</h6>
                            <a :href="`http://127.0.0.1:8000/storage/${requisition.attachment}`" target="_blank" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center">
                                <i class="fa-solid fa-paperclip me-2"></i> View Attachment
                            </a>
                        </div>

                        <h6 class="text-uppercase text-muted small fw-bold mb-3 mt-5">Line Items</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="small text-secondary">Item Name</th>
                                        <th class="small text-secondary text-end" style="width: 120px;">Price</th>
                                        <th class="small text-secondary text-center" style="width: 80px;">Qty</th>
                                        <th class="small text-secondary text-end" style="width: 140px;">Total ({{ requisition.currency }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in requisition.items" :key="index">
                                        <td class="fw-medium">{{ item.name }}</td>
                                        <td class="text-end text-muted">{{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(item.price).toLocaleString() }}</td>
                                        <td class="text-center">{{ item.qty }}</td>
                                        <td class="text-end fw-bold">{{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ (item.price * item.qty).toLocaleString() }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <td colspan="3" class="text-end fw-bold text-uppercase small text-secondary">Total Cost</td>
                                        <td class="text-end fw-bold fs-5 text-primary">
                                            {{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(requisition.total_price).toLocaleString() }}
                                        </td>
                                    </tr>
                                    <tr v-if="requisition.currency === 'USD'" class="bg-light">
                                        <td colspan="4" class="text-end small text-muted border-top-0 pt-0">
                                            Converted: Rp{{ (requisition.total_price * requisition.exchange_rate).toLocaleString() }} (Rate: {{ requisition.exchange_rate }})
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div v-if="requisition.cost_centers && requisition.cost_centers.length > 0" class="mt-4 border-top pt-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Cost Center Allocation</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <span v-for="(cc, index) in requisition.cost_centers" :key="index" class="badge bg-light text-dark border p-2">
                                    {{ cc.department }} ({{ cc.percentage }}%)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div v-if="requisition.status === 'Pending' && ((currentUser.role === 'manager' && requisition.approval_stage === 'Manager') || (currentUser.role === 'admin' && requisition.approval_stage === 'VP') || (currentUser.role === 'finance' && requisition.approval_stage === 'Finance Director'))" class="card border-0 shadow-sm mb-4 border-top border-primary border-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">{{ requisition.approval_stage }} Action Required</h6>
                        <p class="small text-muted mb-4">Review the justification and attached files before making a decision.</p>
                        
                        <div v-if="!showRejectInput" class="d-flex gap-2">
                            <button @click="updateStatus('Approved')" class="btn btn-success flex-grow-1 fw-bold" :disabled="isProcessing">
                                <i class="fa-solid fa-check me-1"></i> Approve
                            </button>
                            <button @click="showRejectInput = true" class="btn btn-outline-danger flex-grow-1 fw-bold" :disabled="isProcessing">
                                <i class="fa-solid fa-xmark me-1"></i> Reject
                            </button>
                        </div>

                        <div v-else class="animate__animated animate__fadeIn">
                            <label class="form-label small fw-bold text-danger">Reason for Rejection</label>
                            <textarea v-model="rejectReason" class="form-control mb-3" rows="3" placeholder="Explain why this was rejected..." required></textarea>
                            <div class="d-flex gap-2">
                                <button @click="updateStatus('Rejected')" class="btn btn-danger flex-grow-1" :disabled="isProcessing || !rejectReason">Confirm Reject</button>
                                <button @click="showRejectInput = false" class="btn btn-light text-muted" :disabled="isProcessing">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="requisition.status === 'Approved' && (currentUser.role === 'finance' || currentUser.role === 'admin')" class="card border-0 shadow-sm mb-4 border-top border-info border-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Finance Action Required</h6>
                        <p class="small text-muted mb-4">This request has been fully approved by the chain of command. Process the payment or generate the PO, then mark as paid.</p>
                        <button @click="updateStatus('Paid')" class="btn btn-info w-100 fw-bold text-white" :disabled="isProcessing">
                            <i class="fa-solid fa-file-invoice-dollar me-2"></i> Mark as Paid
                        </button>
                    </div>
                </div>

                <div v-if="requisition.reason" class="card border-0 shadow-sm" :class="requisition.status === 'Rejected' ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10'">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-2" :class="requisition.status === 'Rejected' ? 'text-danger' : 'text-success'">
                            <i class="fa-solid me-2" :class="requisition.status === 'Rejected' ? 'fa-circle-exclamation' : 'fa-comment-dots'"></i>
                            {{ requisition.status === 'Rejected' ? 'Rejection Notes' : 'Approval Notes' }}
                        </h6>
                        <p class="small mb-0 text-dark">{{ requisition.reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>