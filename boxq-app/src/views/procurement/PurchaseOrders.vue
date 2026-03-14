<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface PurchaseOrder {
    _id?: string | { $oid: string };
    id?: string;
    po_number: string;
    status: string;
    total_amount: number;
    pdf_url: string;
    created_at: string;
}

interface Requisition {
    _id?: string | { $oid: string };
    id?: string;
    department: string;
    total_price: number;
    status: string;
}

interface Vendor {
    _id?: string | { $oid: string };
    id?: string;
    name: string;
}

interface Identifiable {
    _id?: string | { $oid: string };
    id?: string;
}

const pos = ref<PurchaseOrder[]>([]);
const approvedReqs = ref<Requisition[]>([]);
const vendors = ref<Vendor[]>([]);
const loading = ref(true);
const showModal = ref(false);
const submitting = ref(false);

const form = ref({
    requisition_id: '',
    vendor_id: ''
});

const getSafeId = (item: Identifiable) => {
    if (item.id) return String(item.id);
    if (item._id) return typeof item._id === 'object' ? String(item._id.$oid) : String(item._id);
    return Math.random().toString();
};

const fetchData = async () => {
    try {
        const [poRes, reqRes, venRes] = await Promise.all([
            api.get('/purchase-orders'),
            api.get('/requisitions'),
            api.get('/vendors')
        ]);
        
        pos.value = poRes.data;
        vendors.value = venRes.data;
        approvedReqs.value = reqRes.data.filter((r: Requisition) => r.status === 'Approved');
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const generatePO = async () => {
    submitting.value = true;
    try {
        await api.post('/purchase-orders', form.value);
        await fetchData();
        showModal.value = false;
        form.value = { requisition_id: '', vendor_id: '' };
    } catch (error) {
        console.error(error);
        alert('Failed to generate PO.');
    } finally {
        submitting.value = false;
    }
};

const openPdf = (url: string) => {
    window.open(`http://127.0.0.1:8000${url}`, '_blank');
};

onMounted(() => {
    fetchData();
});
</script>

<script lang="ts">
export default {
    name: 'PurchaseOrders'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Purchase Orders</h3>
                <p class="text-muted mb-0">Generate and manage official vendor POs.</p>
            </div>
            <button @click="showModal = true" class="btn btn-primary shadow-sm px-4 fw-bold">
                <i class="fa-solid fa-file-invoice me-2"></i>Create PO
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
                            <th class="ps-4 text-uppercase small fw-bold text-secondary py-3">PO Number</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Date Generated</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Amount</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Status</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3 pe-4 text-end">Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="po in pos" :key="getSafeId(po)">
                            <td class="ps-4 font-monospace fw-bold text-dark">{{ po.po_number }}</td>
                            <td class="text-muted small">{{ new Date(po.created_at).toLocaleDateString() }}</td>
                            <td class="fw-bold text-dark">${{ Number(po.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                            <td><span class="badge bg-success bg-opacity-75">{{ po.status }}</span></td>
                            <td class="pe-4 text-end">
                                <button v-if="po.pdf_url" @click="openPdf(po.pdf_url)" class="btn btn-sm btn-outline-dark px-3 fw-bold">
                                    <i class="fa-solid fa-download me-1"></i> PDF
                                </button>
                                <span v-else class="text-muted small">Generating...</span>
                            </td>
                        </tr>
                        <tr v-if="pos.length === 0">
                            <td colspan="5" class="text-center py-5 text-muted">No Purchase Orders generated yet.</td>
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
                        <h5 class="modal-title fw-bold">Generate Purchase Order</h5>
                        <button type="button" class="btn-close shadow-none" @click="showModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form @submit.prevent="generatePO">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary">Select Approved Requisition</label>
                                <select v-model="form.requisition_id" class="form-select" required>
                                    <option value="" disabled>-- Select Requisition --</option>
                                    <option v-for="req in approvedReqs" :key="getSafeId(req)" :value="getSafeId(req)">
                                        #{{ getSafeId(req).slice(-6).toUpperCase() }} - {{ req.department }} (${{ req.total_price }})
                                    </option>
                                </select>
                                <div v-if="approvedReqs.length === 0" class="form-text text-danger mt-1">
                                    No approved requisitions available to process.
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary">Assign Vendor</label>
                                <select v-model="form.vendor_id" class="form-select" required :disabled="vendors.length === 0">
                                    <option value="" disabled>-- Select Vendor --</option>
                                    <option v-for="ven in vendors" :key="getSafeId(ven)" :value="getSafeId(ven)">
                                        {{ ven.name }}
                                    </option>
                                </select>
                                <div v-if="vendors.length === 0" class="form-text text-warning mt-1">
                                    <i class="fa-solid fa-circle-exclamation me-1"></i>You must add a vendor first in the Directory.
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary py-2 fw-bold" :disabled="submitting || approvedReqs.length === 0 || vendors.length === 0">
                                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="fa-solid fa-file-pdf me-2"></i>
                                    {{ submitting ? 'Generating PDF...' : 'Generate PO PDF' }}
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