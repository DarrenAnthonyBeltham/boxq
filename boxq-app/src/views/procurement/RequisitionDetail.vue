<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
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
    subtotal: number;
    has_tax: boolean;
    tax_amount: number;
    total_price: number;
    currency: string;
    exchange_rate: number;
    cost_centers: CostCenter[];
    status: string;
    approval_stage?: string;
    reason?: string;
    attachment?: string;
    is_over_budget?: boolean;
    invoice_attachment?: string;
    invoice_amount?: number;
    vendor_bank_code?: string;
    vendor_account_number?: string;
    vendor_account_name?: string;
    paid_by?: string;
    paid_at?: string;
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

const invoiceFile = ref<File | null>(null);
const invoiceAmountInput = ref<number | null>(null);
const bankCodeInput = ref('');
const accountNumberInput = ref('');
const accountNameInput = ref('');
const isUploadingInvoice = ref(false);
const paymentNotes = ref('');
let pollingInterval: any = null;

const indonesianBanks = [
    { code: 'BCA', name: 'Bank Central Asia (BCA)' },
    { code: 'MANDIRI', name: 'Bank Mandiri' },
    { code: 'BNI', name: 'Bank Negara Indonesia (BNI)' },
    { code: 'BRI', name: 'Bank Rakyat Indonesia (BRI)' },
    { code: 'PERMATA', name: 'Bank Permata' },
    { code: 'CIMB', name: 'CIMB Niaga' }
];

onMounted(async () => {
    const userData = localStorage.getItem('user');
    if (userData) {
        currentUser.value = JSON.parse(userData);
    }
    await fetchRequisition();
});

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});

const fetchRequisition = async () => {
    try {
        const response = await api.get(`/requisitions/${route.params.id}`);
        requisition.value = response.data;
        
        if (requisition.value?.status === 'Processing Payment') {
            if (!pollingInterval) {
                pollingInterval = setInterval(fetchRequisition, 3000);
            }
        } else {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }
    } catch (error) {
        console.error(error);
        if (!pollingInterval) router.push('/');
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

const hasVariance = computed(() => {
    if (!requisition.value || !requisition.value.invoice_amount) return false;
    return Math.abs(requisition.value.invoice_amount - requisition.value.total_price) > 0.01;
});

const updateStatus = async (newStatus: string) => {
    if (newStatus === 'Rejected' && !rejectReason.value) {
        alert("Please provide a reason for rejection.");
        return;
    }

    let reasonToSubmit = newStatus === 'Rejected' ? rejectReason.value : null;
    
    if (newStatus === 'Paid' && hasVariance.value) {
        reasonToSubmit = `Price Variance Justification: ${paymentNotes.value}`;
    }

    isProcessing.value = true;
    try {
        await api.patch(`/requisitions/${route.params.id}/status`, {
            status: newStatus,
            reason: reasonToSubmit
        });
        await fetchRequisition();
        showRejectInput.value = false;
        rejectReason.value = '';
        paymentNotes.value = '';
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response) {
            alert(error.response.data?.message || "Failed to update status.");
        } else {
            alert("Failed to update status.");
        }
    } finally {
        isProcessing.value = false;
    }
};

const handleInvoiceSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        invoiceFile.value = target.files[0] || null;
    } else {
        invoiceFile.value = null;
    }
};

const submitInvoice = async () => {
    if (!invoiceFile.value || !invoiceAmountInput.value || !bankCodeInput.value || !accountNumberInput.value || !accountNameInput.value) {
        alert("Please complete all invoice and bank details.");
        return;
    }
    
    isUploadingInvoice.value = true;
    try {
        const formData = new FormData();
        formData.append('invoice', invoiceFile.value);
        formData.append('invoice_amount', String(invoiceAmountInput.value));
        formData.append('vendor_bank_code', bankCodeInput.value);
        formData.append('vendor_account_number', accountNumberInput.value);
        formData.append('vendor_account_name', accountNameInput.value);
        
        await api.post(`/requisitions/${route.params.id}/invoice`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        await fetchRequisition();
        invoiceFile.value = null;
        invoiceAmountInput.value = null;
        bankCodeInput.value = '';
        accountNumberInput.value = '';
        accountNameInput.value = '';
        const fileInput = document.getElementById('invoiceUpload') as HTMLInputElement;
        if (fileInput) fileInput.value = '';
    } catch (error) {
        alert("Failed to upload invoice. Ensure all fields are correct.");
    } finally {
        isUploadingInvoice.value = false;
    }
};

const isApprovedMatch = computed(() => {
    const s = requisition.value?.status;
    return s === 'Approved' || s === 'PO Created' || s === 'Received' || s === 'Processing Payment' || s === 'Paid';
});

const isPOMatch = computed(() => {
    const s = requisition.value?.status;
    return s === 'PO Created' || s === 'Received' || s === 'Processing Payment' || s === 'Paid';
});

const isGRNMatch = computed(() => {
    const s = requisition.value?.status;
    return s === 'Received' || s === 'Processing Payment' || s === 'Paid';
});

const hasInvoice = computed(() => {
    return !!requisition.value?.invoice_attachment;
});

const canPay = computed(() => {
    return isApprovedMatch.value && isPOMatch.value && isGRNMatch.value && hasInvoice.value;
});

const canSubmitPayment = computed(() => {
    if (!canPay.value) return false;
    if (hasVariance.value && paymentNotes.value.trim() === '') return false;
    return true;
});

const getStatusBadge = (status: string) => {
    switch(status) {
        case 'Approved': return 'bg-success';
        case 'Rejected': return 'bg-danger';
        case 'PO Created': return 'bg-info text-dark';
        case 'Received': return 'bg-primary';
        case 'Processing Payment': return 'bg-warning text-dark';
        case 'Payment Failed': return 'bg-danger';
        case 'Paid': return 'bg-dark text-white';
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
                            <div class="text-end d-flex flex-column align-items-end gap-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span v-if="requisition.is_over_budget" class="badge bg-danger px-3 py-2 fs-6 shadow-sm border border-danger">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> OVER BUDGET
                                    </span>
                                    <span class="badge px-3 py-2 fs-6 shadow-sm border" :class="getStatusBadge(requisition.status)">
                                        {{ requisition.status }}
                                    </span>
                                </div>
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
                                <i class="fa-solid fa-paperclip me-2"></i> View Original Request File
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
                                        <td colspan="3" class="text-end fw-bold text-uppercase small text-secondary">Subtotal</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(requisition.subtotal || 0).toLocaleString() }}
                                        </td>
                                    </tr>
                                    <tr class="bg-light">
                                        <td colspan="3" class="text-end fw-bold text-uppercase small text-secondary border-top-0 pt-0">VAT / PPN (11%)</td>
                                        <td class="text-end fw-bold text-dark border-top-0 pt-0">
                                            {{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(requisition.tax_amount || 0).toLocaleString() }}
                                            <span v-if="requisition.has_tax === false" class="text-muted small ms-1">(Exempt)</span>
                                        </td>
                                    </tr>
                                    <tr class="bg-light">
                                        <td colspan="3" class="text-end fw-bold text-uppercase small text-secondary border-top-0 pt-0">Grand Total</td>
                                        <td class="text-end fw-bold fs-5 text-primary border-top-0 pt-0">
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

                <div v-if="['Approved', 'PO Created', 'Received', 'Processing Payment', 'Paid', 'Payment Failed'].includes(requisition.status) && ['finance', 'admin', 'manager'].includes(currentUser.role)" class="card border-0 shadow-sm mb-4 border-top border-dark border-3">
                    <div class="card-body p-4">
                        <div v-if="requisition.status === 'Paid'" class="alert alert-success border-0 mb-0 text-start shadow-sm bg-success bg-opacity-10">
                            <div class="d-flex align-items-center mb-3 border-bottom border-success border-opacity-25 pb-3">
                                <div class="bg-success text-white rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 42px; height: 42px;">
                                    <i class="fa-solid fa-check-double fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-success mb-0">Payment Complete</h6>
                                    <span class="small text-muted">Lifecycle closed successfully.</span>
                                </div>
                            </div>
                            <div class="small text-dark">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted fw-bold">Processed By:</span>
                                    <span class="fw-bold">{{ requisition.paid_by || 'Unknown' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted fw-bold">Bank Reference:</span>
                                    <span class="fw-bold">{{ requisition.vendor_bank_code }} - {{ requisition.vendor_account_number }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted fw-bold">Date & Time:</span>
                                    <span class="fw-bold">{{ requisition.paid_at ? formatDate(requisition.paid_at) : 'N/A' }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3 border-top pt-2 border-success border-opacity-25">
                                    <span class="text-muted fw-bold">Final Amount Paid:</span>
                                    <span class="fw-bold fs-6 text-success">{{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(requisition.invoice_amount || requisition.total_price).toLocaleString() }}</span>
                                </div>
                            </div>
                            <a v-if="hasInvoice" :href="`http://127.0.0.1:8000/storage/${requisition.invoice_attachment}`" target="_blank" class="btn btn-sm btn-outline-success w-100 mt-3 fw-bold">
                                <i class="fa-solid fa-file-pdf me-1"></i> View Vendor Invoice
                            </a>
                        </div>
                        
                        <div v-else-if="requisition.status === 'Processing Payment'" class="alert alert-warning border-0 mb-0 text-start shadow-sm bg-warning bg-opacity-10">
                            <div class="d-flex align-items-center mb-3 border-bottom border-warning border-opacity-50 pb-3">
                                <div class="spinner-border text-warning me-3" role="status"></div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-0">Processing Transfer...</h6>
                                    <span class="small text-muted">Awaiting confirmation from Xendit.</span>
                                </div>
                            </div>
                            <div class="small text-dark">
                                <p class="mb-0">The bank transfer to <strong>{{ requisition.vendor_bank_code }}</strong> (Account: {{ requisition.vendor_account_number }}) has been initiated. This screen will update when it confirms the transaction.</p>
                            </div>
                        </div>

                        <div v-else-if="requisition.status === 'Payment Failed'" class="alert alert-danger border-0 mb-0 text-start shadow-sm bg-danger bg-opacity-10">
                            <div class="d-flex align-items-center mb-3 border-bottom border-danger border-opacity-25 pb-3">
                                <div class="bg-danger text-white rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 42px; height: 42px;">
                                    <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-danger mb-0">Bank Transfer Failed</h6>
                                    <span class="small text-muted">Please check vendor details and retry.</span>
                                </div>
                            </div>
                            <button @click="updateStatus('Paid')" class="btn btn-danger w-100 fw-bold py-2 shadow-sm mt-2" :disabled="isProcessing">
                                <i class="fa-solid fa-rotate-right me-2"></i> Retry Payment
                            </button>
                        </div>
                        
                        <div v-else>
                            <h6 class="fw-bold mb-1">Financial Processing</h6>
                            <p class="small text-muted mb-4">Complete the 3-Way Match before remitting funds.</p>
                            
                            <div class="bg-light rounded p-3 mb-4">
                                <h6 class="text-uppercase small fw-bold text-secondary mb-3">The 3-Way Match</h6>
                                
                                <div class="d-flex align-items-center mb-2" :class="isApprovedMatch ? 'text-success' : 'text-muted'">
                                    <i class="fa-solid me-3 fs-5" :class="isApprovedMatch ? 'fa-circle-check' : 'fa-circle'"></i>
                                    <div>
                                        <div class="fw-bold small">Requisition Approved</div>
                                        <div class="small opacity-75" style="font-size: 0.75rem;">Authorized by Management</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-2" :class="isPOMatch ? 'text-success' : 'text-muted'">
                                    <i class="fa-solid me-3 fs-5" :class="isPOMatch ? 'fa-circle-check' : 'fa-circle'"></i>
                                    <div>
                                        <div class="fw-bold small">Purchase Order Created</div>
                                        <div class="small opacity-75" style="font-size: 0.75rem;">Sent to Vendor</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center" :class="isGRNMatch ? 'text-success' : 'text-muted'">
                                    <i class="fa-solid me-3 fs-5" :class="isGRNMatch ? 'fa-circle-check' : 'fa-circle'"></i>
                                    <div>
                                        <div class="fw-bold small">Goods Received Note (GRN)</div>
                                        <div class="small opacity-75" style="font-size: 0.75rem;">Items confirmed on-site</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-uppercase small fw-bold text-secondary mb-2">Vendor Invoice & Bank Details</h6>
                                
                                <div v-if="hasInvoice" class="d-flex align-items-center justify-content-between p-2 border rounded bg-success bg-opacity-10 border-success mb-2">
                                    <div class="d-flex align-items-center text-success">
                                        <i class="fa-solid fa-file-invoice fs-4 me-2"></i>
                                        <div>
                                            <span class="small fw-bold d-block mb-1">Total: {{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(requisition.invoice_amount).toLocaleString() }}</span>
                                            <span class="small opacity-75 d-block">{{ requisition.vendor_bank_code }} - {{ requisition.vendor_account_number }}</span>
                                        </div>
                                    </div>
                                    <a :href="`http://127.0.0.1:8000/storage/${requisition.invoice_attachment}`" target="_blank" class="btn btn-sm btn-success">View</a>
                                </div>

                                <div v-else class="d-flex flex-column gap-2 mt-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted fw-bold" style="width: 140px;">Invoice Total</span>
                                        <input type="number" v-model="invoiceAmountInput" class="form-control" placeholder="0.00" step="0.01">
                                    </div>
                                    
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted fw-bold" style="width: 140px;">Bank Code</span>
                                        <select v-model="bankCodeInput" class="form-select">
                                            <option value="" disabled>Select Bank...</option>
                                            <option v-for="bank in indonesianBanks" :key="bank.code" :value="bank.code">{{ bank.name }}</option>
                                        </select>
                                    </div>

                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted fw-bold" style="width: 140px;">Account Num</span>
                                        <input type="text" v-model="accountNumberInput" class="form-control" placeholder="e.g. 1234567890">
                                    </div>

                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted fw-bold" style="width: 140px;">Account Name</span>
                                        <input type="text" v-model="accountNameInput" class="form-control" placeholder="Vendor Entity Name">
                                    </div>

                                    <div class="input-group input-group-sm mt-2">
                                        <input type="file" id="invoiceUpload" @change="handleInvoiceSelect" class="form-control" accept=".pdf,.jpg,.png">
                                        <button @click="submitInvoice" class="btn btn-dark" type="button" :disabled="!invoiceFile || !invoiceAmountInput || !bankCodeInput || !accountNumberInput || !accountNameInput || isUploadingInvoice">
                                            <span v-if="isUploadingInvoice" class="spinner-border spinner-border-sm" role="status"></span>
                                            <span v-else>Upload & Lock</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="canPay" class="mt-3">
                                <div v-if="hasVariance" class="alert alert-warning border-warning border-2 bg-warning bg-opacity-10 p-3 mb-3">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Price Variance Detected</h6>
                                    <p class="small text-muted mb-2">The uploaded invoice amount ({{ requisition.currency === 'USD' ? '$' : 'Rp' }}{{ Number(requisition.invoice_amount).toLocaleString() }}) differs from the requested total. Please provide a justification before payment.</p>
                                    <textarea v-model="paymentNotes" class="form-control border-warning shadow-sm bg-white" rows="2" placeholder="Explain the price difference..." required></textarea>
                                </div>
                                <button @click="updateStatus('Paid')" class="btn btn-primary w-100 fw-bold py-3 text-white transition-all shadow-sm" :disabled="isProcessing || !canSubmitPayment">
                                    <i class="fa-solid fa-money-bill-transfer me-2"></i> Initiate Transfer to {{ requisition.vendor_bank_code }}
                                </button>
                            </div>
                            <button v-else class="btn btn-secondary w-100 fw-bold py-3 text-white transition-all disabled">
                                <i class="fa-solid fa-lock me-2"></i> Complete Match to Pay
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="requisition.reason" class="card border-0 shadow-sm" :class="requisition.status === 'Rejected' ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10'">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-2" :class="requisition.status === 'Rejected' ? 'text-danger' : 'text-success'">
                            <i class="fa-solid me-2" :class="requisition.status === 'Rejected' ? 'fa-circle-exclamation' : 'fa-comment-dots'"></i>
                            {{ requisition.status === 'Rejected' ? 'Rejection Notes' : 'Approval & Payment Notes' }}
                        </h6>
                        <p class="small mb-0 text-dark">{{ requisition.reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>