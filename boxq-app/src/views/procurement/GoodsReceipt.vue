<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import axios from 'axios';
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
    vendor_account_name?: string;
}

interface PaginationData {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

const router = useRouter();
const toast = useToast();

const receipts = ref<Requisition[]>([]);
const loading = ref(true);
const isProcessing = ref<string | number | null>(null);

const searchQuery = ref('');
const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15
});

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const fetchReceipts = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/grn', {
            params: {
                page: page,
                search: searchQuery.value
            }
        });
        
        receipts.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total,
            per_page: response.data.per_page
        };
    } catch (error) {
        toast.error("Failed to load incoming deliveries.");
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchReceipts();
});

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchReceipts(1);
    }, 500);
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        fetchReceipts(page);
    }
};

const confirmReceipt = async (id: string | number) => {
    if (!confirm("Are you sure you want to officially confirm these goods have physically arrived? This will notify Finance to release payment.")) {
        return;
    }

    isProcessing.value = id;
    try {
        await api.post('/grn', { requisition_id: id, delivery_notes: 'Confirmed full delivery upon physical inspection.' });
        toast.success('Goods Receipt Note securely logged. Finance notified.');
        fetchReceipts(pagination.value.current_page);
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            toast.error(error.response?.data?.message || "Failed to confirm receipt.");
        } else {
            toast.error("Failed to confirm receipt.");
        }
    } finally {
        isProcessing.value = null;
    }
};

const viewDetails = (id: string | number) => {
    router.push(`/requisition/${id}`);
};

const formatId = (id: string | number) => {
    const str = String(id);
    return 'PO-' + str.slice(-8).toUpperCase();
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<script lang="ts"> export default { name: 'GoodsReceiptView' } </script>

<template>
    <MainLayout>
        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1">Incoming Deliveries</h3>
            <p class="text-muted mb-0">Confirm physical receipt of goods to authorize vendor payments.</p>
        </div>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="input-group" style="max-width: 400px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input v-model="searchQuery" type="text" class="form-control border-start-0 bg-light" placeholder="Search POs, vendors, or items...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="py-3 px-4 border-0">PO Number</th>
                            <th class="py-3 border-0">Vendor</th>
                            <th class="py-3 border-0">Primary Item</th>
                            <th class="py-3 border-0">Total Qty</th>
                            <th class="py-3 border-0">Status</th>
                            <th class="py-3 border-0">Ordered On</th>
                            <th class="py-3 px-4 border-0 text-end">Delivery Action</th>
                        </tr>
                    </thead>
                    <tbody v-if="loading">
                        <tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>
                    </tbody>
                    <tbody v-else-if="receipts.length === 0">
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-1 text-light mb-3 d-block"></i>No pending deliveries found.</td></tr>
                    </tbody>
                    <tbody v-else class="border-top-0">
                        <tr v-for="req in receipts" :key="req._id || req.id" class="border-bottom">
                            <td class="px-4 py-3"><span class="text-dark fw-bold">{{ formatId(req._id || req.id) }}</span></td>
                            <td class="py-3 fw-medium text-dark">{{ req.vendor_account_name || 'Pending Vendor Assign' }}</td>
                            <td class="py-3 text-muted">{{ req.items && req.items.length > 0 ? req.items[0]?.name : 'N/A' }} <span v-if="req.items && req.items.length > 1" class="small text-secondary">(+{{ req.items.length - 1 }} more)</span></td>
                            <td class="py-3 fw-bold">{{ (req.items || []).reduce((sum, item) => sum + (item.qty || 0), 0) }} Units</td>
                            <td class="py-3">
                                <span v-if="req.status === 'PO Created'" class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fa-solid fa-truck-fast me-1"></i> In Transit</span>
                                <span v-else class="badge bg-primary px-3 py-2 rounded-pill"><i class="fa-solid fa-box-check me-1"></i> Received</span>
                            </td>
                            <td class="py-3 text-muted small">{{ formatDate(req.created_at) }}</td>
                            <td class="px-4 py-3 text-end d-flex justify-content-end gap-2">
                                <button @click="viewDetails(req._id || req.id)" class="btn btn-light border btn-sm px-3 shadow-sm text-dark">Details</button>
                                <button v-if="req.status === 'PO Created'" @click="confirmReceipt(req._id || req.id)" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm" :disabled="isProcessing === (req._id || req.id)">
                                    <span v-if="isProcessing === (req._id || req.id)" class="spinner-border spinner-border-sm"></span>
                                    <span v-else>Confirm Receipt</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="!loading && pagination.last_page > 1" class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Showing page <strong>{{ pagination.current_page }}</strong> of <strong>{{ pagination.last_page }}</strong></span>
                <div class="btn-group shadow-sm">
                    <button @click="goToPage(pagination.current_page - 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === 1"><i class="fa-solid fa-chevron-left"></i></button>
                    <button @click="goToPage(pagination.current_page + 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === pagination.last_page"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>