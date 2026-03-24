<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../services/api';
import VendorLayout from '../../layouts/VendorLayout.vue';

interface Item { name: string; qty: number; price: number; }
interface Order {
    _id: string; id: number; items: Item[]; total_price: number; currency: string; status: string; created_at: string; invoice_attachment?: string;
}

const toast = useToast();
const orders = ref<Order[]>([]);
const loading = ref(true);
const searchQuery = ref('');

const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const selectedOrder = ref<Order | null>(null);
const invoiceFile = ref<File | null>(null);
const invoiceAmountInput = ref<number | null>(null);
const bankCodeInput = ref('');
const accountNumberInput = ref('');
const accountNameInput = ref('');
const isUploadingInvoice = ref(false);

const indonesianBanks = [
    { code: 'BCA', name: 'Bank Central Asia (BCA)' }, { code: 'MANDIRI', name: 'Bank Mandiri' },
    { code: 'BNI', name: 'Bank Negara Indonesia (BNI)' }, { code: 'BRI', name: 'Bank Rakyat Indonesia (BRI)' }
];

const fetchOrders = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/vendor-portal/orders', { params: { page, search: searchQuery.value } });
        orders.value = response.data.data;
        pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
    } catch (error) {
        toast.error("Failed to load orders.");
    } finally {
        loading.value = false;
    }
};

onMounted(() => fetchOrders());

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchOrders(1), 500);
});

const handleInvoiceSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    invoiceFile.value = target.files?.[0] || null;
};

const openUploadModal = (order: Order) => {
    selectedOrder.value = order;
    invoiceFile.value = null; invoiceAmountInput.value = null; bankCodeInput.value = ''; accountNumberInput.value = ''; accountNameInput.value = '';
};

const submitInvoice = async () => {
    if (!selectedOrder.value || !invoiceFile.value || !invoiceAmountInput.value || !bankCodeInput.value || !accountNumberInput.value) return;
    
    isUploadingInvoice.value = true;
    try {
        const formData = new FormData();
        formData.append('invoice', invoiceFile.value);
        formData.append('invoice_amount', String(invoiceAmountInput.value));
        formData.append('vendor_bank_code', bankCodeInput.value);
        formData.append('vendor_account_number', accountNumberInput.value);
        formData.append('vendor_account_name', accountNameInput.value);
        
        await api.post(`/vendor-portal/orders/${selectedOrder.value._id || selectedOrder.value.id}/invoice`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        toast.success("Invoice successfully submitted for processing!");
        selectedOrder.value = null;
        fetchOrders(pagination.value.current_page);
    } catch (error) {
        toast.error("Failed to upload invoice.");
    } finally {
        isUploadingInvoice.value = false;
    }
};

const formatId = (id: string | number) => 'PO-' + String(id).slice(-8).toUpperCase();
const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        'PO Created': 'bg-warning text-dark', 'Received': 'bg-info text-dark',
        'Processing Payment': 'bg-primary text-white', 'Paid': 'bg-success text-white', 'Reconciled': 'bg-success text-white'
    };
    return map[status] || 'bg-secondary text-white';
};
</script>

<script lang="ts"> export default { name: 'VendorDashboard' } </script>

<template>
    <VendorLayout>
        <div class="mb-4 text-center text-md-start fade-in">
            <h2 class="fw-bold text-dark mb-1">Purchase Orders</h2>
            <p class="text-muted">Manage your fulfilling orders and submit invoices securely.</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden fade-in">
            <div class="card-header bg-white border-bottom p-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input v-model="searchQuery" type="text" class="form-control border-start-0 bg-light" placeholder="Search PO Number...">
                </div>
            </div>

            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small fw-bold text-uppercase">
                        <tr>
                            <th class="py-3 px-4 border-0">PO Number</th>
                            <th class="py-3 border-0">Items</th>
                            <th class="py-3 border-0">Total Value</th>
                            <th class="py-3 border-0">Status</th>
                            <th class="py-3 border-0">Date</th>
                            <th class="py-3 px-4 border-0 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody v-if="loading">
                        <tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>
                    </tbody>
                    <tbody v-else-if="orders.length === 0">
                        <tr><td colspan="6" class="text-center py-5 text-muted">No purchase orders found for your account.</td></tr>
                    </tbody>
                    <tbody v-else>
                        <tr v-for="order in orders" :key="order._id || order.id" class="border-bottom hover-row transition-all">
                            <td class="px-4 py-3 fw-bold text-dark">{{ formatId(order._id || order.id) }}</td>
                            <td class="py-3 text-muted">{{ order.items?.[0]?.name || 'Unknown' }} <span v-if="order.items?.length > 1" class="small text-secondary">(+{{ order.items.length - 1 }})</span></td>
                            <td class="py-3 fw-bold">{{ order.currency === 'USD' ? '$' : 'Rp' }}{{ Number(order.total_price).toLocaleString() }}</td>
                            <td class="py-3"><span class="badge rounded-pill px-3 py-2 fw-medium" :class="getStatusBadge(order.status)">{{ order.status }}</span></td>
                            <td class="py-3 text-muted small">{{ formatDate(order.created_at) }}</td>
                            <td class="px-4 py-3 text-end">
                                <button v-if="!order.invoice_attachment && ['PO Created', 'Received'].includes(order.status)" @click="openUploadModal(order)" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm rounded-pill"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Submit Invoice</button>
                                <span v-else-if="order.invoice_attachment" class="badge bg-light text-success border border-success px-3 py-2 rounded-pill"><i class="fa-solid fa-check-circle me-1"></i> Invoice Processed</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-block d-lg-none p-3 bg-light">
                <div v-if="loading" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
                <div v-else-if="orders.length === 0" class="text-center py-4 text-muted">No orders found.</div>
                <div v-else class="d-flex flex-column gap-3">
                    <div v-for="order in orders" :key="order._id || order.id" class="card border-0 shadow-sm rounded-4 overflow-hidden smooth-card">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark fs-5">{{ formatId(order._id || order.id) }}</span>
                                <span class="badge rounded-pill" :class="getStatusBadge(order.status)">{{ order.status }}</span>
                            </div>
                            <p class="text-muted mb-2 small text-truncate"><i class="fa-solid fa-box me-2"></i>{{ order.items?.[0]?.name || 'Unknown' }}</p>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                                <span class="fw-bold fs-5 text-primary">{{ order.currency === 'USD' ? '$' : 'Rp' }}{{ Number(order.total_price).toLocaleString() }}</span>
                                <span class="text-muted small">{{ formatDate(order.created_at) }}</span>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 p-3 pt-0">
                            <button v-if="!order.invoice_attachment && ['PO Created', 'Received'].includes(order.status)" @click="openUploadModal(order)" class="btn btn-primary w-100 fw-bold shadow-sm rounded-pill py-2"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Invoice</button>
                            <div v-else-if="order.invoice_attachment" class="text-center text-success small fw-bold bg-success bg-opacity-10 py-2 rounded-pill"><i class="fa-solid fa-check-circle me-2"></i>Invoice Submitted</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div v-if="!loading && pagination.last_page > 1" class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Page <strong>{{ pagination.current_page }}</strong> of {{ pagination.last_page }}</span>
                <div class="btn-group shadow-sm">
                    <button @click="fetchOrders(pagination.current_page - 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === 1"><i class="fa-solid fa-chevron-left"></i></button>
                    <button @click="fetchOrders(pagination.current_page + 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === pagination.last_page"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div v-if="selectedOrder" class="modal-backdrop fade show" style="opacity: 0.5;"></div>
        <div v-if="selectedOrder" class="modal fade show d-block" tabindex="-1" @click.self="selectedOrder = null">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden modal-zoom">
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>Submit Invoice</h5>
                        <button type="button" class="btn-close btn-close-white shadow-none" @click="selectedOrder = null"></button>
                    </div>
                    <div class="modal-body p-4 bg-light">
                        <div class="alert alert-info border-0 shadow-sm mb-4 bg-info bg-opacity-10 d-flex align-items-center">
                            <i class="fa-solid fa-circle-info fs-4 text-info me-3"></i>
                            <div class="small text-dark">Uploading invoice for PO <strong>{{ formatId(selectedOrder._id || selectedOrder.id) }}</strong>. Expected value: <strong>{{ selectedOrder.currency === 'USD' ? '$' : 'Rp' }}{{ Number(selectedOrder.total_price).toLocaleString() }}</strong>.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Actual Invoice Amount</label>
                            <input type="number" v-model="invoiceAmountInput" class="form-control form-control-lg shadow-sm border-0" placeholder="0.00" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Bank / Routing Code</label>
                            <select v-model="bankCodeInput" class="form-select form-select-lg shadow-sm border-0">
                                <option value="" disabled>Select receiving bank...</option>
                                <option v-for="bank in indonesianBanks" :key="bank.code" :value="bank.code">{{ bank.name }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Account Number</label>
                            <input type="text" v-model="accountNumberInput" class="form-control form-control-lg shadow-sm border-0" placeholder="e.g. 1234567890">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Beneficiary Name</label>
                            <input type="text" v-model="accountNameInput" class="form-control form-control-lg shadow-sm border-0" placeholder="Your Company Name">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Attach PDF/Image</label>
                            <input type="file" @change="handleInvoiceSelect" class="form-control form-control-lg shadow-sm border-0 bg-white" accept=".pdf,.jpg,.png">
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 bg-white">
                        <button type="button" class="btn btn-light rounded-pill px-4" @click="selectedOrder = null" :disabled="isUploadingInvoice">Cancel</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" @click="submitInvoice" :disabled="!invoiceFile || !invoiceAmountInput || !bankCodeInput || !accountNumberInput || !accountNameInput || isUploadingInvoice">
                            <span v-if="isUploadingInvoice" class="spinner-border spinner-border-sm me-2"></span>
                            <span v-else><i class="fa-solid fa-paper-plane me-2"></i>Submit Document</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>

<style scoped>
.fade-in {
    animation: fadeIn 0.4s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.smooth-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.smooth-card:active {
    transform: scale(0.98);
}
.hover-row:hover {
    background-color: #f8f9fa !important;
}
.modal-zoom {
    animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes zoomIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15) !important;
}
</style>