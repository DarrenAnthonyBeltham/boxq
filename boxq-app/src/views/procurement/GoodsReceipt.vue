<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface GoodsReceipt {
    _id?: string | { $oid: string };
    id?: string;
    grn_number: string;
    requisition_id: string;
    received_by: string;
    notes: string;
    created_at: string;
}

interface Requisition {
    _id?: string | { $oid: string };
    id?: string;
    department: string;
    requester: string;
    total_price: number;
    status: string;
}

interface Identifiable {
    _id?: string | { $oid: string };
    id?: string;
}

const grns = ref<GoodsReceipt[]>([]);
const pendingReqs = ref<Requisition[]>([]);
const loading = ref(true);
const showModal = ref(false);
const submitting = ref(false);

const form = ref({
    requisition_id: '',
    notes: ''
});

const getSafeId = (item: Identifiable) => {
    if (item.id) return String(item.id);
    if (item._id) return typeof item._id === 'object' ? String(item._id.$oid) : String(item._id);
    return Math.random().toString();
};

const fetchData = async () => {
    try {
        const [grnRes, reqRes] = await Promise.all([
            api.get('/grn'),
            api.get('/requisitions')
        ]);
        
        grns.value = grnRes.data;
        pendingReqs.value = reqRes.data.filter((r: Requisition) => r.status === 'PO Created' || r.status === 'Approved');
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const createGRN = async () => {
    submitting.value = true;
    try {
        await api.post('/grn', form.value);
        await fetchData();
        showModal.value = false;
        form.value = { requisition_id: '', notes: '' };
    } catch (error) {
        console.error(error);
        alert('Failed to log goods receipt.');
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    fetchData();
});
</script>

<script lang="ts">
export default {
    name: 'GoodsReceipts'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Goods Receiving</h3>
                <p class="text-muted mb-0">Confirm physical delivery of ordered items.</p>
            </div>
            <button @click="showModal = true" class="btn btn-dark shadow-sm px-4 fw-bold">
                <i class="fa-solid fa-box-open me-2"></i>Log Receipt
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        <div v-else class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-uppercase small fw-bold text-secondary py-3">GRN Number</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Date Received</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Received By</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Req ID</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3 pe-4 text-end">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="grn in grns" :key="getSafeId(grn)">
                            <td class="ps-4 font-monospace fw-bold text-dark">{{ grn.grn_number }}</td>
                            <td class="text-muted small">{{ new Date(grn.created_at).toLocaleDateString() }}</td>
                            <td class="fw-bold text-dark">{{ grn.received_by }}</td>
                            <td class="font-monospace text-muted small">#{{ String(grn.requisition_id).slice(-6).toUpperCase() }}</td>
                            <td class="pe-4 text-end text-muted small text-truncate" style="max-width: 200px;">
                                {{ grn.notes || 'N/A' }}
                            </td>
                        </tr>
                        <tr v-if="grns.length === 0">
                            <td colspan="5" class="text-center py-5 text-muted">No goods have been received yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="modal-backdrop fade show" style="opacity: 0.5;"></div>
        <div v-if="showModal" class="modal d-block" tabindex="-1" @click.self="showModal = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                        <h5 class="modal-title fw-bold">Log Goods Receipt</h5>
                        <button type="button" class="btn-close shadow-none" @click="showModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form @submit.prevent="createGRN">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary">Incoming Order</label>
                                <select v-model="form.requisition_id" class="form-select" required>
                                    <option value="" disabled>-- Select Arrived Order --</option>
                                    <option v-for="req in pendingReqs" :key="getSafeId(req)" :value="getSafeId(req)">
                                        #{{ getSafeId(req).slice(-6).toUpperCase() }} - {{ req.requester }} ({{ req.department }})
                                    </option>
                                </select>
                                <div v-if="pendingReqs.length === 0" class="form-text text-danger mt-1">
                                    No pending orders awaiting arrival.
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary">Inspection Notes (Optional)</label>
                                <textarea v-model="form.notes" class="form-control" rows="3" placeholder="e.g. Box slightly damaged, but contents intact."></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark py-2 fw-bold" :disabled="submitting || pendingReqs.length === 0">
                                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="fa-solid fa-check me-2"></i>
                                    {{ submitting ? 'Logging...' : 'Confirm Delivery' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.modal { background-color: rgba(0,0,0,0.1); }
</style>