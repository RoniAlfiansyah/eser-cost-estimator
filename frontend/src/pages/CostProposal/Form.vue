<template>
  <div class="cost-proposal-page">
    <b-breadcrumb>
      <b-breadcrumb-item>COST PROPOSAL</b-breadcrumb-item>
      <b-breadcrumb-item to="/app/cost-proposals">Daftar Proposal</b-breadcrumb-item>
      <b-breadcrumb-item active>{{ isEditMode ? 'Edit Cost Proposal' : 'Buat Cost Proposal' }}</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">{{ isEditMode ? 'Edit' : 'Buat' }} <span class="fw-semi-bold">Cost Proposal</span></h2>
        <p class="page-subtitle">Bangun proposal dari basic cost approved lalu tentukan markup pada masing-masing item.</p>
      </div>
      <div class="page-header-actions">
        <b-button
          v-if="isEditMode"
          variant="warning"
          :disabled="isSubmitting || lineItems.length === 0 || !canExportClientPdf"
          @click="exportClientPdf"
        >
          Export Client PDF
        </b-button>
        <b-button
          v-if="isEditMode"
          variant="success"
          :disabled="isSubmitting || lineItems.length === 0"
          @click="exportDetail"
        >
          Export Internal
        </b-button>
        <b-button variant="default" @click="$router.push('/app/cost-proposals')">
          Kembali
        </b-button>
      </div>
    </div>

    <Widget title="<h5>Header <span class='fw-semi-bold'>Proposal</span></h5>" customHeader class="mb-xlg">
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <b-row>
        <b-col md="4">
          <b-form-group label="Pilih Basic Cost Approved">
            <b-form-select v-model="form.basic_cost_id" :disabled="isEditMode || !canEditDocument" :options="basicCostOptions" @change="onBasicCostChange" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Nomor Proposal">
            <b-form-input v-model="form.proposal_number" :disabled="!canEditDocument" placeholder="CP-2026-001" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Tanggal Proposal">
            <b-form-input v-model="form.proposal_date" :disabled="!canEditDocument" type="date" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Berlaku Sampai">
            <b-form-input v-model="form.valid_until" :disabled="!canEditDocument" type="date" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Nama Klien">
            <b-form-input v-model="form.client_name" :disabled="!canEditDocument" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Nama Proyek">
            <b-form-input v-model="form.project_name" :disabled="!canEditDocument" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Lokasi">
            <b-form-input v-model="form.location" :disabled="!canEditDocument" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Jenis Pekerjaan">
            <b-form-input v-model="form.work_type_names" readonly />
          </b-form-group>
        </b-col>
        <b-col md="12">
          <b-form-group label="Catatan Proposal">
            <b-form-textarea v-model="form.notes" :disabled="!canEditDocument" rows="2" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Yth / Attention">
            <b-form-input v-model="form.attention_name" :disabled="!canEditDocument" placeholder="Contoh: Bp. Ari Bahro" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Perihal">
            <b-form-input v-model="form.proposal_subject" :disabled="!canEditDocument" placeholder="Penawaran pekerjaan..." />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Lampiran">
            <b-form-input v-model="form.attachment_label" :disabled="!canEditDocument" placeholder="Rincian Anggaran Biaya" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Penandatangan">
            <b-form-select v-model="form.signatory_id" :disabled="!canEditDocument" :options="signatoryOptions" />
          </b-form-group>
        </b-col>
        <b-col v-if="isEditMode && form.signatory_id" md="6">
          <b-form-group label="Link Dokumen Public">
            <b-form-input :value="getPublicDocumentUrl()" readonly />
          </b-form-group>
        </b-col>
      </b-row>
    </Widget>

    <Widget v-if="isEditMode" title="<h5>Approval <span class='fw-semi-bold'>Cost Proposal</span></h5>" customHeader class="mb-xlg">
      <div class="approval-summary">
        <div class="approval-meta">
          <span class="status-badge" :class="statusClass">{{ statusLabel }}</span>
          <span v-if="documentMeta.submitted_at" class="approval-meta-text">
            Diajukan {{ documentMeta.submitted_at }} oleh {{ documentMeta.submitted_by_name || 'pengguna' }}
          </span>
          <span v-if="documentMeta.reviewed_at" class="approval-meta-text">
            Direview {{ documentMeta.reviewed_at }} oleh {{ documentMeta.reviewed_by_name || 'atasan' }}
          </span>
        </div>
        <div v-if="documentMeta.review_notes" class="approval-note-box">
          <strong>Catatan Review:</strong> {{ documentMeta.review_notes }}
        </div>
      </div>

      <b-form-group
        v-if="canRequestRevisionDocument || canApproveDocument || canIssueDocument"
        label="Catatan Approval"
        class="mt-md"
      >
        <b-form-textarea
          v-model="approvalActionNote"
          rows="3"
          placeholder="Tulis catatan approval, revisi, atau penerbitan dokumen"
        />
      </b-form-group>

      <div class="form-actions">
        <b-button v-if="canSubmitDocument" variant="warning" @click="submitForReview">
          Submit for Review
        </b-button>
        <b-button v-if="canRequestRevisionDocument" variant="danger" @click="requestRevision">
          Request Revision
        </b-button>
        <b-button v-if="canApproveDocument" variant="success" @click="approveDocument">
          Approve
        </b-button>
        <b-button v-if="canIssueDocument" variant="primary" @click="issueDocument">
          Mark as Issued
        </b-button>
      </div>

      <div v-if="approvalHistory.length" class="approval-history mt-lg">
        <h6 class="fw-semi-bold mb-md">Riwayat Approval</h6>
        <div v-for="item in approvalHistory" :key="item.id" class="approval-history-item">
          <div class="approval-history-top">
            <strong>{{ formatHistoryAction(item.action) }}</strong>
            <span>{{ item.created_at || '-' }}</span>
          </div>
          <div class="approval-history-meta">
            <span>{{ item.actor_name || 'Sistem' }}</span>
            <span>{{ item.status_from || '-' }} -> {{ item.status_to }}</span>
          </div>
          <p v-if="item.note" class="approval-history-note">{{ item.note }}</p>
        </div>
      </div>
    </Widget>

    <Widget title="<h5>Strategi <span class='fw-semi-bold'>Markup</span></h5>" customHeader class="mb-xlg">
      <div class="markup-summary">
        Setiap item proposal bisa dimarkup sendiri tanpa mengubah data Basic Cost. Nilai proposal dihitung dari harga dasar per item ditambah markup per item.
      </div>
    </Widget>

    <Widget v-if="lineItems.length > 0" title="<h5>Ringkasan <span class='fw-semi-bold'>Komersial</span></h5>" customHeader class="mb-xlg">
      <b-row>
        <b-col md="4">
          <div class="summary-card">
            <div class="summary-label">Basic Cost Total</div>
            <div class="summary-value">{{ formatCurrency(calculatedTotals.basic_cost_total) }}</div>
          </div>
        </b-col>
        <b-col md="4">
          <div class="summary-card summary-card-accent">
            <div class="summary-label">Total Markup</div>
            <div class="summary-value summary-value-split">
              <span>{{ formatCurrency(calculatedTotals.total_markup_amount) }}</span>
              <span class="summary-percentage">{{ formatPercentage(calculatedTotals.markup_percentage) }}</span>
            </div>
          </div>
        </b-col>
        <b-col md="4">
          <div class="summary-card summary-card-success">
            <div class="summary-label">Grand Total Proposal</div>
            <div class="summary-value">{{ formatCurrency(calculatedTotals.grand_total) }}</div>
          </div>
        </b-col>
      </b-row>

      <div class="table-responsive mt-lg">
        <table class="table table-hover proposal-table proposal-summary-table">
          <thead>
            <tr>
              <th>Kategori</th>
              <th>Basic Cost</th>
              <th>Total Markup</th>
              <th>Total Proposal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in groupedCategoryTotals" :key="row.category_name">
              <td>{{ row.category_name }}</td>
              <td>{{ formatCurrency(row.basic_total) }}</td>
              <td>{{ formatCurrency(row.markup_total) }}</td>
              <td>{{ formatCurrency(row.proposal_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Widget>

    <Widget title="<h5>Terms <span class='fw-semi-bold'>& Notes</span></h5>" customHeader class="mb-xlg">
      <b-row>
        <b-col md="4">
          <b-form-group label="Assumptions">
            <b-form-textarea v-model="form.assumptions" :disabled="!canEditDocument" rows="4" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Exclusions">
            <b-form-textarea v-model="form.exclusions" :disabled="!canEditDocument" rows="4" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Payment Terms">
            <b-form-textarea v-model="form.payment_terms" :disabled="!canEditDocument" rows="4" />
          </b-form-group>
        </b-col>
      </b-row>
    </Widget>

    <Widget title="<h5>Item <span class='fw-semi-bold'>Cost Proposal</span></h5>" customHeader>
      <div class="table-responsive">
        <table class="table table-hover proposal-table">
          <thead>
            <tr>
              <th>Kategori</th>
              <th>Subkategori</th>
              <th>Item</th>
              <th>Satuan</th>
              <th>Qty</th>
              <th>Duration</th>
              <th>Basic Unit Price</th>
              <th>Basic Total</th>
              <th>Markup Type</th>
              <th>Markup Value</th>
              <th>Markup Amount</th>
              <th>Proposal Unit Price</th>
              <th>Proposal Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="lineItems.length === 0">
              <td colspan="13" class="text-center py-4">Pilih basic cost approved untuk memuat item.</td>
            </tr>
            <tr v-for="item in lineItems" :key="item.id || `${item.item_name}-${item.sort_order}`">
              <td>{{ item.category_name }}</td>
              <td>{{ item.subcategory_name || '-' }}</td>
              <td>{{ item.item_name }}</td>
              <td>{{ item.unit_symbol || '-' }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ item.duration }}</td>
              <td>{{ formatCurrency(item.basic_unit_price) }}</td>
              <td>{{ formatCurrency(item.basic_total_price) }}</td>
              <td class="markup-input-cell">
                <b-form-select v-model="item.markup_type" :disabled="!canEditDocument" :options="adjustmentTypeOptions" />
              </td>
              <td class="markup-input-cell">
                <b-form-input v-model="item.markup_value" :disabled="!canEditDocument" type="number" step="0.01" min="0" />
              </td>
              <td>{{ formatCurrency(getItemMarkupAmount(item)) }}</td>
              <td>{{ formatCurrency(getItemProposalUnitPrice(item)) }}</td>
              <td>{{ formatCurrency(getItemProposalTotalPrice(item)) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="lineItems.length > 0">
            <tr class="grand-total-row">
              <td colspan="12" class="text-end">Basic Cost Total</td>
              <td>{{ formatCurrency(calculatedTotals.basic_cost_total) }}</td>
            </tr>
            <tr>
              <td colspan="12" class="text-end">Total Markup</td>
              <td>{{ formatCurrency(calculatedTotals.total_markup_amount) }}</td>
            </tr>
            <tr class="proposal-total-row">
              <td colspan="12" class="text-end">Grand Total Proposal</td>
              <td>{{ formatCurrency(calculatedTotals.grand_total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="form-actions mt-lg">
        <b-button variant="primary" :disabled="isSubmitting || !canEditDocument" @click="submitForm">
          {{ isSubmitting ? 'Menyimpan...' : (isEditMode ? 'Update Cost Proposal' : 'Simpan Cost Proposal') }}
        </b-button>
        <b-button variant="inverse" class="ms-2" @click="$router.push('/app/cost-proposals')">
          Batal
        </b-button>
      </div>
    </Widget>
  </div>
</template>

<script>
import axios from 'axios';
import ExcelJS from 'exceljs';
import QRCode from 'qrcode';
import logoImage from '@/assets/company/eser-geosurvey-logo.png';
import signSealImage from '@/assets/company/eser_geosurvey-transparent.png';
import Widget from '@/components/Widget/Widget';

const COMPANY_PROFILE = {
  name: 'PT. Eser Geosurvey Indonesia',
  address: 'Menara Apartemen Edelweiss, Apartemen Rajawali, Town House No.20 - Jl. Rajawali Selatan I No.63 6, RT.6/RW.2, Gn. Sahari Utara, Kecamatan Sawah Besar, Kota Jakarta Pusat',
  email: 'office@esergeosurvey.com',
};

export default {
  name: 'CostProposalForm',
  components: { Widget },
  data() {
    return {
      logoImage,
      signSealImage,
      signatoryOptions: [{ value: '', text: 'Pilih penandatangan' }],
      basicCostLookup: {},
      basicCostOptions: [{ value: '', text: 'Pilih Basic Cost Approved' }],
      lineItems: [],
      isSubmitting: false,
      errorMessage: '',
      approvalActionNote: '',
      approvalHistory: [],
      documentMeta: {
        status: 'draft',
        submitted_at: null,
        submitted_by_name: null,
        reviewed_at: null,
        reviewed_by_name: null,
        review_notes: null,
      },
      permissionFlags: {
        can_edit: true,
        can_submit: false,
        can_request_revision: false,
        can_approve: false,
        can_issue: false,
      },
      form: {
        basic_cost_id: '',
        proposal_number: '',
        proposal_date: new Date().toISOString().slice(0, 10),
        valid_until: '',
        client_name: '',
        project_name: '',
        location: '',
        work_type_names: '',
        notes: '',
        attention_name: '',
        proposal_subject: '',
        attachment_label: 'Rincian Anggaran Biaya',
        assumptions: '',
        exclusions: '',
        payment_terms: '',
        signatory_id: '',
        signatory_signature_data_url: '',
        signatory_signature_url: '',
        status: 'draft',
      },
      adjustmentTypeOptions: [
        { value: 'percent', text: 'Persentase' },
        { value: 'fixed', text: 'Nominal Tetap' },
      ],
    };
  },
  computed: {
    isEditMode() {
      return !!this.$route.params.id;
    },
    calculatedTotals() {
      const basicCostTotal = this.lineItems.reduce((sum, item) => sum + this.getItemBasicTotal(item), 0);
      const totalMarkupAmount = this.lineItems.reduce((sum, item) => sum + this.getItemMarkupTotalAmount(item), 0);
      const grandTotal = this.lineItems.reduce((sum, item) => sum + this.getItemProposalTotalPrice(item), 0);
      const markupPercentage = basicCostTotal > 0 ? (totalMarkupAmount / basicCostTotal) * 100 : 0;

      return {
        basic_cost_total: basicCostTotal,
        total_markup_amount: totalMarkupAmount,
        grand_total: grandTotal,
        markup_percentage: markupPercentage,
      };
    },
    groupedCategoryTotals() {
      const groups = this.lineItems.reduce((acc, item) => {
        const key = item.category_name || '-';
        if (!acc[key]) {
          acc[key] = {
            category_name: key,
            basic_total: 0,
            markup_total: 0,
            proposal_total: 0,
          };
        }

        acc[key].basic_total += this.getItemBasicTotal(item);
        acc[key].markup_total += this.getItemMarkupTotalAmount(item);
        acc[key].proposal_total += this.getItemProposalTotalPrice(item);
        return acc;
      }, {});

      return Object.values(groups);
    },
    clientPdfRows() {
      const rows = [];
      let runningNumber = 1;

      this.groupedCategoryTotals.forEach((group) => {
        this.lineItems
          .filter((item) => (item.category_name || '-') === group.category_name)
          .forEach((item) => {
            rows.push({
              type: 'item',
              no: runningNumber,
              description: item.item_name || '-',
              unit: item.unit_symbol || '-',
              quantity: this.formatNumber(item.quantity),
              duration: this.formatNumber(item.duration),
              unitPrice: this.formatCurrencyPlain(this.getItemProposalUnitPrice(item)),
              total: this.formatCurrencyPlain(this.getItemProposalTotalPrice(item)),
            });
            runningNumber += 1;
          });

        rows.push({
          type: 'subtotal',
          label: `Subtotal ${group.category_name}`,
          total: this.formatCurrencyPlain(group.proposal_total),
        });
      });

      return rows;
    },
    statusLabel() {
      const labels = {
        draft: 'Draft',
        submitted: 'Submitted',
        revision: 'Revision',
        approved: 'Approved',
        issued: 'Issued',
        cancelled: 'Cancelled',
      };

      return labels[this.form.status] || 'Draft';
    },
    statusClass() {
      return `status-${this.form.status || 'draft'}`;
    },
    canEditDocument() {
      return !this.isEditMode || this.permissionFlags.can_edit;
    },
    canSubmitDocument() {
      return this.isEditMode && this.permissionFlags.can_submit;
    },
    canRequestRevisionDocument() {
      return this.isEditMode && this.permissionFlags.can_request_revision;
    },
    canApproveDocument() {
      return this.isEditMode && this.permissionFlags.can_approve;
    },
    canIssueDocument() {
      return this.isEditMode && this.permissionFlags.can_issue;
    },
    canExportClientPdf() {
      return this.isEditMode && ['approved', 'issued'].includes(this.form.status || 'draft');
    },
    selectedSignatoryOption() {
      return this.signatoryOptions.find((item) => item.value === this.form.signatory_id) || null;
    },
    effectiveSignatorySignatureUrl() {
      return this.form.signatory_signature_url || (this.selectedSignatoryOption && this.selectedSignatoryOption.signatureImageUrl) || '';
    },
    effectiveSignatorySignatureDataUrl() {
      return this.form.signatory_signature_data_url || (this.selectedSignatoryOption && this.selectedSignatoryOption.signatureImageDataUrl) || '';
    },
  },
  methods: {
    loadSignatories() {
      return axios.get('/signatories', {
        params: { is_active: 1 },
      }).then((response) => {
        const signatories = response.data.data || [];
        this.signatoryOptions = [{ value: '', text: 'Pilih penandatangan' }].concat(
          signatories.map((item) => ({
            value: String(item.id),
            text: `${item.name} - ${item.position_title}`,
            signatureImageDataUrl: item.signature_image_data_url || '',
            signatureImageUrl: item.signature_image_url || '',
          })),
        );

        if (!this.form.signatory_id && signatories.length > 0) {
          this.form.signatory_id = String(signatories[0].id);
        }
      });
    },
    loadApprovedBasicCosts() {
      return axios.get('/basic-costs')
        .then((response) => {
          const approved = (response.data.data || []).filter((item) => item.status === 'approved');
          this.basicCostLookup = approved.reduce((acc, item) => {
            acc[String(item.id)] = item;
            return acc;
          }, {});
          this.basicCostOptions = [{ value: '', text: 'Pilih Basic Cost Approved' }].concat(
            approved.map((item) => ({
              value: String(item.id),
              text: `#${item.id} - ${item.project_name} (${item.client_name})`,
            })),
          );
        });
    },
    loadDocument() {
      if (!this.isEditMode) {
        return;
      }

      axios.get(`/cost-proposals/${this.$route.params.id}`)
        .then((response) => {
          const data = response.data.data;
          this.approvalActionNote = '';
          this.permissionFlags = {
            can_edit: !!data.can_edit,
            can_submit: !!data.can_submit,
            can_request_revision: !!data.can_request_revision,
            can_approve: !!data.can_approve,
            can_issue: !!data.can_issue,
          };
          this.documentMeta = {
            status: data.status || 'draft',
            submitted_at: data.submitted_at || null,
            submitted_by_name: data.submitted_by_name || null,
            reviewed_at: data.reviewed_at || null,
            reviewed_by_name: data.reviewed_by_name || null,
            review_notes: data.review_notes || null,
          };
          this.approvalHistory = data.approval_history || [];
          this.form = {
            basic_cost_id: String(data.basic_cost_id),
            proposal_number: data.proposal_number || '',
            proposal_date: data.proposal_date || new Date().toISOString().slice(0, 10),
            valid_until: data.valid_until || '',
            client_name: data.client_name || '',
            project_name: data.project_name || '',
            location: data.location || '',
            work_type_names: data.work_type_names || '',
            notes: data.notes || '',
            attention_name: data.attention_name || '',
            proposal_subject: data.proposal_subject || '',
            attachment_label: data.attachment_label || 'Rincian Anggaran Biaya',
            assumptions: data.assumptions || '',
            exclusions: data.exclusions || '',
            payment_terms: data.payment_terms || '',
            signatory_id: data.signatory_id != null ? String(data.signatory_id) : '',
            signatory_name: data.signatory_name || '',
            signatory_title: data.signatory_title || '',
            signatory_signature_data_url: data.signatory_signature_data_url || '',
            signatory_signature_url: data.signatory_signature_url || '',
            public_token: data.public_token || '',
            status: data.status || 'draft',
          };
          this.lineItems = (data.items || []).map((item, index) => this.normalizeLineItem(item, index));
          this.populateProposalDefaults();
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal memuat cost proposal.');
        });
    },
    onBasicCostChange() {
      if (!this.form.basic_cost_id) {
        this.lineItems = [];
        return;
      }

      axios.get(`/basic-costs/${this.form.basic_cost_id}`)
        .then((response) => {
          const data = response.data.data;
          this.approvalActionNote = '';
          this.approvalHistory = [];
          this.permissionFlags = {
            can_edit: true,
            can_submit: false,
            can_request_revision: false,
            can_approve: false,
            can_issue: false,
          };
          this.form.client_name = data.client_name || '';
          this.form.project_name = data.project_name || '';
          this.form.location = data.location || '';
          this.form.work_type_names = (data.work_type_names || []).join(', ');
          this.form.status = 'draft';
          this.documentMeta = {
            status: 'draft',
            submitted_at: null,
            submitted_by_name: null,
            reviewed_at: null,
            reviewed_by_name: null,
            review_notes: null,
          };
          this.lineItems = (data.items || []).map((item, index) => this.normalizeLineItem(item, index));
          this.populateProposalDefaults();
          if (!this.form.proposal_number) {
            this.form.proposal_number = this.generateProposalNumber(String(data.id));
          }
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal memuat basic cost sumber.');
        });
    },
    generateProposalNumber(basicCostId) {
      const month = `${new Date().getMonth() + 1}`.padStart(2, '0');
      const year = new Date().getFullYear();
      return `CP-${year}${month}-${basicCostId}`;
    },
    populateProposalDefaults() {
      if (!this.form.proposal_subject && this.form.project_name) {
        this.form.proposal_subject = `Penawaran ${this.form.project_name}`;
      }
      if (!this.form.attachment_label) {
        this.form.attachment_label = 'Rincian Anggaran Biaya';
      }
      if (!this.form.attention_name && this.form.client_name) {
        this.form.attention_name = this.form.client_name;
      }
    },
    normalizeLineItem(item, index) {
      const basicUnitPrice = Number(item.basic_unit_price != null ? item.basic_unit_price : item.unit_price || 0);
      const basicTotalPrice = Number(item.basic_total_price != null ? item.basic_total_price : item.total_price || 0);
      const markupType = item.markup_type || 'percent';
      const markupValue = Number(item.markup_value || 0);

      return {
        id: item.id || null,
        basic_cost_item_id: item.basic_cost_item_id != null ? Number(item.basic_cost_item_id) : null,
        category_name: item.category_name || '-',
        subcategory_name: item.subcategory_name || '',
        item_name: item.item_name || '',
        unit_symbol: item.unit_symbol || '',
        quantity: Number(item.quantity || 0),
        duration: Number(item.duration || 0),
        basic_unit_price: basicUnitPrice,
        basic_total_price: basicTotalPrice,
        markup_type: markupType,
        markup_value: markupValue,
        sort_order: item.sort_order != null ? Number(item.sort_order) : index,
      };
    },
    getItemBasicTotal(item) {
      return Number(item.basic_total_price || 0);
    },
    getItemMarkupAmount(item) {
      const basicUnitPrice = Number(item.basic_unit_price || 0);
      const markupValue = Number(item.markup_value || 0);
      return item.markup_type === 'fixed'
        ? markupValue
        : (basicUnitPrice * markupValue) / 100;
    },
    getItemProposalUnitPrice(item) {
      return Number(item.basic_unit_price || 0) + this.getItemMarkupAmount(item);
    },
    getItemProposalTotalPrice(item) {
      const multiplier = Number(item.quantity || 0) * Number(item.duration || 0);
      return multiplier * this.getItemProposalUnitPrice(item);
    },
    getItemMarkupTotalAmount(item) {
      const multiplier = Number(item.quantity || 0) * Number(item.duration || 0);
      return multiplier * this.getItemMarkupAmount(item);
    },
    formatCurrency(value) {
      return `Rp ${Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    formatCurrencyPlain(value) {
      return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    },
    formatPercentage(value) {
      return `${Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
    },
    getPublicDocumentUrl() {
      if (!this.isEditMode || !this.form.public_token) {
        return '';
      }

      return `${window.location.origin}${window.location.pathname}#/quotation/${this.form.public_token}`;
    },
    selectedSignatoryText() {
      const selected = this.signatoryOptions.find((item) => item.value === this.form.signatory_id);
      return selected ? selected.text : '-';
    },
    async exportClientPdf() {
      if (!this.isEditMode || this.lineItems.length === 0) {
        return;
      }
      if (!this.canExportClientPdf) {
        this.$toasted.show('PDF client hanya bisa diexport setelah proposal approved atau issued.', { type: 'error' });
        return;
      }

      try {
        const pdfMakeModule = await import('pdfmake/build/pdfmake');
        const pdfFontsModule = await import('pdfmake/build/vfs_fonts');
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const pdfFonts = pdfFontsModule.default || pdfFontsModule;
        pdfMake.vfs = (pdfFonts.pdfMake && pdfFonts.pdfMake.vfs) || pdfFonts.vfs;
        const signatureDataUrlPromise = this.effectiveSignatorySignatureDataUrl
          ? Promise.resolve(this.effectiveSignatorySignatureDataUrl)
          : (this.effectiveSignatorySignatureUrl
            ? this.loadImageAsDataUrl(this.effectiveSignatorySignatureUrl).catch(() => null)
            : Promise.resolve(null));
        const [logoDataUrl, qrDataUrl, signatureDataUrl, signSealDataUrl] = await Promise.all([
          this.loadImageAsDataUrl(this.logoImage),
          QRCode.toDataURL(this.getPublicDocumentUrl(), {
            margin: 1,
            width: 110,
            color: {
              dark: '#1F4E78',
              light: '#FFFFFF',
            },
          }),
          signatureDataUrlPromise,
          this.loadImageAsDataUrl(this.signSealImage),
        ]);
        const docDefinition = this.buildClientPdfDefinition(logoDataUrl, qrDataUrl, signatureDataUrl, signSealDataUrl);
        const safeProposalNumber = (this.form.proposal_number || 'quotation')
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-|-$/g, '')
          .slice(0, 50) || 'quotation';

        pdfMake.createPdf(docDefinition).download(`quotation-${safeProposalNumber}.pdf`);
        this.$toasted.show('Export client PDF berhasil dibuat.', { type: 'success' });
      } catch (error) {
        this.errorMessage = error && error.message
          ? `Export PDF gagal: ${error.message}`
          : 'Export PDF gagal. Silakan coba lagi.';
        this.$toasted.show('Export PDF gagal. Silakan coba lagi.', { type: 'error' });
      }
    },
    buildClientPdfDefinition(logoDataUrl, qrDataUrl, signatureDataUrl = null, signSealDataUrl = null) {
      const tableBody = [
        [
          { text: 'No', style: 'tableHeader', alignment: 'center' },
          { text: 'Description', style: 'tableHeader' },
          { text: 'Unit', style: 'tableHeader', alignment: 'center' },
          { text: 'Qty', style: 'tableHeader', alignment: 'right' },
          { text: 'Duration', style: 'tableHeader', alignment: 'right' },
          { text: 'Unit Price', style: 'tableHeader', alignment: 'right' },
          { text: 'Amount', style: 'tableHeader', alignment: 'right' },
        ],
      ];

      this.clientPdfRows.forEach((row) => {
        if (row.type === 'item') {
          tableBody.push([
            { text: String(row.no), alignment: 'center' },
            { text: row.description },
            { text: row.unit, alignment: 'center' },
            { text: row.quantity, alignment: 'right' },
            { text: row.duration, alignment: 'right' },
            { text: row.unitPrice, alignment: 'right' },
            { text: row.total, alignment: 'right' },
          ]);
          return;
        }

        tableBody.push([
          { text: '' },
          { text: row.label, colSpan: 5, bold: true, fillColor: '#EAF2F8', margin: [4, 6, 4, 6] },
          {},
          {},
          {},
          {},
          { text: row.total, bold: true, alignment: 'right', fillColor: '#EAF2F8', margin: [4, 6, 4, 6] },
        ]);
      });

      tableBody.push([
        { text: '' },
        { text: 'GRAND TOTAL', colSpan: 5, bold: true, color: '#1F4E78', fillColor: '#D9EAD3', margin: [4, 8, 4, 8] },
        {},
        {},
        {},
        {},
        {
          text: this.formatCurrencyPlain(this.calculatedTotals.grand_total),
          bold: true,
          color: '#1F4E78',
          fillColor: '#D9EAD3',
          alignment: 'right',
          margin: [4, 8, 4, 8],
        },
      ]);

      const termsColumns = [];
      if (this.form.assumptions) {
        termsColumns.push({
          width: '*',
          stack: [
            { text: 'Assumptions', style: 'sectionTitle' },
            { text: this.form.assumptions, style: 'bodyText' },
          ],
        });
      }
      if (this.form.exclusions) {
        termsColumns.push({
          width: '*',
          stack: [
            { text: 'Exclusions', style: 'sectionTitle' },
            { text: this.form.exclusions, style: 'bodyText' },
          ],
        });
      }
      if (this.form.payment_terms) {
        termsColumns.push({
          width: '*',
          stack: [
            { text: 'Payment Terms', style: 'sectionTitle' },
            { text: this.form.payment_terms, style: 'bodyText' },
          ],
        });
      }

      return {
        pageSize: 'A4',
        pageMargins: [36, 42, 36, 42],
        footer: (currentPage, pageCount) => ({
          margin: [36, 12, 36, 0],
          columns: [
            { text: this.form.proposal_number || 'Quotation', color: '#6B778C', fontSize: 8 },
            { text: `Page ${currentPage} of ${pageCount}`, alignment: 'right', color: '#6B778C', fontSize: 8 },
          ],
        }),
        content: [
          {
            columns: [
              [
                {
                  image: logoDataUrl,
                  width: 180,
                  margin: [0, 0, 0, 8],
                },
                { text: COMPANY_PROFILE.name, style: 'companyName' },
                { text: COMPANY_PROFILE.address, style: 'companyMeta' },
                { text: COMPANY_PROFILE.email, style: 'companyMeta' },
              ],
              [
                { text: `Jakarta, ${this.formatDateDisplay(this.form.proposal_date)}`, alignment: 'right', style: 'dateText' },
              ],
            ],
          },
          {
            canvas: [{ type: 'line', x1: 0, y1: 0, x2: 523, y2: 0, lineWidth: 1.2, lineColor: '#295F98' }],
            margin: [0, 12, 0, 20],
          },
          {
            table: {
              widths: [70, 12, '*'],
              body: [
                [{ text: 'No.', style: 'letterMetaLabel' }, { text: ':', style: 'letterMetaLabel' }, { text: this.form.proposal_number || '-', style: 'letterMetaValue' }],
                [{ text: 'Perihal', style: 'letterMetaLabel' }, { text: ':', style: 'letterMetaLabel' }, { text: this.form.proposal_subject || `Penawaran ${this.form.project_name || 'Pekerjaan'}`, style: 'letterMetaValue' }],
                [{ text: 'Lampiran', style: 'letterMetaLabel' }, { text: ':', style: 'letterMetaLabel' }, { text: this.form.attachment_label || 'Rincian Anggaran Biaya', style: 'letterMetaValue' }],
              ],
            },
            layout: 'noBorders',
            margin: [0, 0, 0, 16],
          },
          {
            stack: [
              { text: 'Kepada,', style: 'bodyText' },
              { text: `Yth. ${this.form.attention_name || this.form.client_name || '-'}`, style: 'recipientTextHighlight' },
            ],
            margin: [0, 0, 0, 16],
          },
          {
            text: `Berdasarkan kebutuhan penawaran untuk pekerjaan ${this.form.proposal_subject || this.form.project_name || 'dimaksud'}, maka kami sampaikan penawaran kami yang memiliki nilai:`,
            style: 'bodyText',
            margin: [0, 0, 0, 14],
          },
          {
            text: `Rp. ${this.formatCurrencyPlain(this.calculatedTotals.grand_total)}`,
            style: 'amountText',
            alignment: 'center',
            margin: [0, 0, 0, 6],
          },
          {
            text: `Terbilang : #${this.terbilang(this.calculatedTotals.grand_total)} Rupiah#`,
            style: 'terbilangText',
            alignment: 'center',
            margin: [0, 0, 0, 14],
          },
          {
            text: this.form.notes || 'Rincian penawaran biaya kami sampaikan pada halaman lampiran.',
            style: 'bodyText',
            margin: [0, 0, 0, 14],
          },
          termsColumns.length > 0
            ? {
              columns: termsColumns,
              columnGap: 18,
              margin: [0, 18, 0, 18],
            }
            : { text: '' },
          {
            text: 'Demikian kami sampaikan, apabila terdapat hal yang perlu didiskusikan, kami siap untuk berdiskusi lebih lanjut.',
            style: 'bodyText',
            margin: [0, 16, 0, 10],
          },
          {
            text: 'Terima kasih atas perhatian dan kepercayaan yang diberikan. Kami berharap dapat menyelesaikan pekerjaan ini dengan baik dan memuaskan.',
            style: 'bodyText',
            margin: [0, 0, 0, 24],
          },
          {
            columns: [
              {
                width: 120,
                stack: [
                  {
                    image: qrDataUrl,
                    width: 92,
                    margin: [0, 0, 0, 4],
                  },
                  { text: 'Scan untuk membuka dokumen proposal', fontSize: 8, color: '#51657D' },
                ],
              },
              { width: '*', text: '' },
              {
                width: 190,
                stack: [
                  { text: 'Hormat kami,', style: 'bodyText' },
                  { text: COMPANY_PROFILE.name, style: 'bodyText', margin: [0, 2, 0, 10] },
                  {
                    stack: [
                      ...(signSealDataUrl ? [{
                        image: signSealDataUrl,
                        width: 122,
                        fit: [122, 92],
                        opacity: 0.65,
                        margin: [18, 0, 0, 0],
                      }] : []),
                      ...(signatureDataUrl ? [{
                        image: signatureDataUrl,
                        width: 120,
                        fit: [120, 52],
                        margin: [0, signSealDataUrl ? -62 : 0, 0, 8],
                      }] : [{ text: '', margin: [0, 0, 0, 52] }]),
                    ],
                    margin: [0, 0, 0, 4],
                  },
                  { text: this.form.signatory_name || this.selectedSignatoryText().split(' - ')[0] || '-', style: 'signatureName' },
                  { text: this.form.signatory_title || this.selectedSignatoryText().split(' - ').slice(1).join(' - ') || '-', style: 'bodyText' },
                ],
              },
            ],
            margin: [0, 6, 0, 0],
          },
          {
            text: '',
            pageBreak: 'before',
          },
          {
            text: 'Rincian Penawaran Biaya',
            style: 'sectionTitle',
            margin: [0, 0, 0, 12],
          },
          {
            table: {
              headerRows: 1,
              widths: [28, '*', 46, 42, 52, 74, 84],
              body: tableBody,
            },
            layout: {
              fillColor: (rowIndex) => (rowIndex === 0 ? '#1F4E78' : null),
              hLineColor: () => '#C9D7E6',
              vLineColor: () => '#C9D7E6',
              hLineWidth: () => 0.8,
              vLineWidth: () => 0.8,
              paddingLeft: () => 6,
              paddingRight: () => 6,
              paddingTop: () => 6,
              paddingBottom: () => 6,
            },
          },
        ],
        styles: {
          companyName: {
            fontSize: 12,
            bold: true,
            color: '#1F4E78',
          },
          companyMeta: {
            fontSize: 8.5,
            color: '#51657D',
            lineHeight: 1.25,
          },
          sectionTitle: {
            fontSize: 10,
            bold: true,
            color: '#1F4E78',
            margin: [0, 0, 0, 6],
          },
          recipientTextHighlight: {
            fontSize: 11,
            bold: true,
            color: '#1F1F1F',
            background: '#FFF176',
            margin: [0, 0, 0, 2],
          },
          amountText: {
            fontSize: 18,
            bold: true,
            color: '#0E2D52',
          },
          terbilangText: {
            fontSize: 10,
            bold: true,
            italics: true,
            color: '#1F1F1F',
          },
          letterMetaLabel: {
            fontSize: 10,
            bold: true,
            color: '#1F1F1F',
            margin: [0, 2, 0, 2],
          },
          letterMetaValue: {
            fontSize: 10,
            color: '#1F1F1F',
            margin: [0, 2, 0, 2],
          },
          dateText: {
            fontSize: 10,
            bold: true,
            color: '#8B5A13',
            margin: [0, 8, 0, 0],
          },
          bodyText: {
            fontSize: 9,
            color: '#1F1F1F',
            lineHeight: 1.35,
          },
          tableHeader: {
            fontSize: 9,
            bold: true,
            color: '#FFFFFF',
            margin: [0, 2, 0, 2],
          },
          signatureName: {
            fontSize: 10,
            bold: true,
            decoration: 'underline',
            color: '#1F1F1F',
            margin: [0, 0, 0, 2],
          },
        },
        defaultStyle: {
          fontSize: 9,
        },
      };
    },
    loadImageAsDataUrl(imageUrl) {
      return fetch(imageUrl)
        .then((response) => response.blob())
        .then((blob) => new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(reader.result);
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        }));
    },
    terbilang(value) {
      const angka = Math.floor(Number(value || 0));
      if (angka === 0) {
        return 'Nol';
      }

      const penyebut = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
      const convert = (n) => {
        if (n < 12) {
          return penyebut[n];
        }
        if (n < 20) {
          return `${convert(n - 10)} Belas`;
        }
        if (n < 100) {
          return `${convert(Math.floor(n / 10))} Puluh ${convert(n % 10)}`.trim();
        }
        if (n < 200) {
          return `Seratus ${convert(n - 100)}`.trim();
        }
        if (n < 1000) {
          return `${convert(Math.floor(n / 100))} Ratus ${convert(n % 100)}`.trim();
        }
        if (n < 2000) {
          return `Seribu ${convert(n - 1000)}`.trim();
        }
        if (n < 1000000) {
          return `${convert(Math.floor(n / 1000))} Ribu ${convert(n % 1000)}`.trim();
        }
        if (n < 1000000000) {
          return `${convert(Math.floor(n / 1000000))} Juta ${convert(n % 1000000)}`.trim();
        }
        if (n < 1000000000000) {
          return `${convert(Math.floor(n / 1000000000))} Miliar ${convert(n % 1000000000)}`.trim();
        }

        return `${convert(Math.floor(n / 1000000000000))} Triliun ${convert(n % 1000000000000)}`.trim();
      };

      return convert(angka).replace(/\s+/g, ' ').trim();
    },
    formatDateDisplay(value) {
      if (!value) {
        return '-';
      }

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      });
    },
    async exportDetail() {
      if (!this.isEditMode || this.lineItems.length === 0) {
        return;
      }

      try {
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Cost Estimator';
        workbook.company = 'Cost Estimator';
        workbook.created = new Date();
        workbook.modified = new Date();

        this.buildExportSummarySheet(workbook);
        this.buildExportDetailSheet(workbook);
        this.buildExportComparisonSheet(workbook);

        const safeProposalNumber = (this.form.proposal_number || 'cost-proposal')
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-|-$/g, '')
          .slice(0, 50) || 'cost-proposal';

        const buffer = await workbook.xlsx.writeBuffer();
        this.downloadExcelFile(buffer, `cost-proposal-${safeProposalNumber}.xlsx`);
        this.$toasted.show('Export proposal berhasil dibuat.', { type: 'success' });
      } catch (error) {
        this.errorMessage = error && error.message
          ? `Export Excel gagal: ${error.message}`
          : 'Export Excel gagal. Silakan coba lagi.';
        this.$toasted.show('Export Excel gagal. Silakan coba lagi.', { type: 'error' });
      }
    },
    buildExportSummarySheet(workbook) {
      const worksheet = workbook.addWorksheet('Ringkasan Proposal', {
        views: [{ state: 'frozen', ySplit: 3 }],
      });

      worksheet.columns = [
        { width: 24 },
        { width: 52 },
      ];

      this.addSheetTitle(
        worksheet,
        'COST PROPOSAL',
        `Proposal No: ${this.form.proposal_number || '-'} | Status: ${this.statusLabel}`,
        2,
      );

      let rowIndex = 4;
      rowIndex = this.addKeyValueSection(worksheet, rowIndex, 'INFORMASI PROPOSAL', [
        ['Proposal Number', this.form.proposal_number || '-'],
        ['Proposal Date', this.form.proposal_date || '-'],
        ['Valid Until', this.form.valid_until || '-'],
        ['Basic Cost Reference', this.form.basic_cost_id || '-'],
        ['Nama Klien', this.form.client_name || '-'],
        ['Nama Proyek', this.form.project_name || '-'],
        ['Lokasi', this.form.location || '-'],
        ['Jenis Pekerjaan', this.form.work_type_names || '-'],
      ]);

      rowIndex += 1;

      rowIndex = this.addKeyValueSection(worksheet, rowIndex, 'TERMS & NOTES', [
        ['Catatan Proposal', this.form.notes || '-'],
        ['Assumptions', this.form.assumptions || '-'],
        ['Exclusions', this.form.exclusions || '-'],
        ['Payment Terms', this.form.payment_terms || '-'],
      ]);

      rowIndex += 1;

      this.addKeyValueSection(worksheet, rowIndex, 'RINGKASAN NILAI', [
        ['Jumlah Item', this.lineItems.length],
        ['Basic Cost Total', Number(this.calculatedTotals.basic_cost_total || 0)],
        ['Total Markup', Number(this.calculatedTotals.total_markup_amount || 0)],
        ['Grand Total Proposal', Number(this.calculatedTotals.grand_total || 0)],
      ], {
        currencyValueRows: [2, 3, 4],
        emphasizeLastRow: true,
      });
    },
    buildExportDetailSheet(workbook) {
      const worksheet = workbook.addWorksheet('Detail Proposal', {
        views: [{ state: 'frozen', ySplit: 4 }],
      });

      worksheet.columns = [
        { header: 'No', key: 'no', width: 8 },
        { header: 'Kategori', key: 'category', width: 20 },
        { header: 'Subkategori', key: 'subcategory', width: 22 },
        { header: 'Item', key: 'item', width: 34 },
        { header: 'Satuan', key: 'unit', width: 12 },
        { header: 'Qty', key: 'quantity', width: 10 },
        { header: 'Duration', key: 'duration', width: 10 },
        { header: 'Basic Unit Price', key: 'basicUnitPrice', width: 18 },
        { header: 'Basic Total', key: 'basicTotal', width: 18 },
        { header: 'Markup Type', key: 'markupType', width: 14 },
        { header: 'Markup Value', key: 'markupValue', width: 16 },
        { header: 'Markup Amount', key: 'markupAmount', width: 18 },
        { header: 'Proposal Unit Price', key: 'proposalUnitPrice', width: 20 },
        { header: 'Proposal Total', key: 'proposalTotal', width: 18 },
      ];

      this.addSheetTitle(
        worksheet,
        'DETAIL COST PROPOSAL',
        `Project: ${this.form.project_name || '-'} | Klien: ${this.form.client_name || '-'} | Proposal: ${this.form.proposal_number || '-'}`,
        14,
      );

      const headerRow = worksheet.addRow([
        'No', 'Kategori', 'Subkategori', 'Item', 'Satuan', 'Qty', 'Duration',
        'Basic Unit Price', 'Basic Total', 'Markup Type', 'Markup Value',
        'Markup Amount', 'Proposal Unit Price', 'Proposal Total',
      ]);
      this.styleTableHeaderRow(headerRow);

      let runningNumber = 1;
      this.groupedCategoryTotals.forEach((group) => {
        this.lineItems
          .filter((item) => (item.category_name || '-') === group.category_name)
          .forEach((item, index) => {
            const row = worksheet.addRow([
              runningNumber,
              item.category_name || '-',
              item.subcategory_name || '-',
              item.item_name || '-',
              item.unit_symbol || '-',
              Number(item.quantity || 0),
              Number(item.duration || 0),
              Number(item.basic_unit_price || 0),
              Number(this.getItemBasicTotal(item) || 0),
              item.markup_type === 'fixed' ? 'Nominal' : 'Persentase',
              item.markup_type === 'fixed'
                ? Number(item.markup_value || 0)
                : `${Number(item.markup_value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`,
              Number(this.getItemMarkupTotalAmount(item) || 0),
              Number(this.getItemProposalUnitPrice(item) || 0),
              Number(this.getItemProposalTotalPrice(item) || 0),
            ]);
            this.styleTableDataRow(row, index % 2 === 1, [6, 7, 8, 9, 11, 12, 13, 14]);
            [8, 9, 12, 13, 14].forEach((cellIndex) => this.applyCurrencyCell(row.getCell(cellIndex)));
            runningNumber += 1;
          });

        const subtotalRow = worksheet.addRow([
          '',
          `Subtotal ${group.category_name}`,
          '',
          '',
          '',
          '',
          '',
          '',
          Number(group.basic_total || 0),
          '',
          '',
          Number(group.markup_total || 0),
          '',
          Number(group.proposal_total || 0),
        ]);
        this.styleSubtotalRow(subtotalRow, [9, 12, 14]);
        [9, 12, 14].forEach((cellIndex) => this.applyCurrencyCell(subtotalRow.getCell(cellIndex)));
      });

      worksheet.addRow([]);
      const grandTotalRow = worksheet.addRow([
        '', 'GRAND TOTAL', '', '', '', '', '', '',
        Number(this.calculatedTotals.basic_cost_total || 0),
        '', '',
        Number(this.calculatedTotals.total_markup_amount || 0),
        '',
        Number(this.calculatedTotals.grand_total || 0),
      ]);
      this.styleGrandTotalRow(grandTotalRow, [9, 12, 14]);
      [9, 12, 14].forEach((cellIndex) => this.applyCurrencyCell(grandTotalRow.getCell(cellIndex)));

      this.applyOuterBorder(worksheet, headerRow.number, grandTotalRow.number, 1, 14);
    },
    buildExportComparisonSheet(workbook) {
      const worksheet = workbook.addWorksheet('Perbandingan', {
        views: [{ state: 'frozen', ySplit: 4 }],
      });

      worksheet.columns = [
        { header: 'Kategori', key: 'category', width: 24 },
        { header: 'Basic Cost', key: 'basic', width: 20 },
        { header: 'Total Markup', key: 'markup', width: 20 },
        { header: 'Proposal Total', key: 'proposal', width: 20 },
        { header: 'Markup %', key: 'markupPercent', width: 16 },
      ];

      this.addSheetTitle(
        worksheet,
        'PERBANDINGAN BASIC COST VS PROPOSAL',
        `Proposal No: ${this.form.proposal_number || '-'} | Ref Basic Cost: ${this.form.basic_cost_id || '-'}`,
        5,
      );

      const headerRow = worksheet.addRow(['Kategori', 'Basic Cost', 'Total Markup', 'Proposal Total', 'Markup %']);
      this.styleTableHeaderRow(headerRow);

      this.groupedCategoryTotals.forEach((group, index) => {
        const markupPercent = Number(group.basic_total || 0) > 0
          ? (Number(group.markup_total || 0) / Number(group.basic_total || 0))
          : 0;
        const row = worksheet.addRow([
          group.category_name,
          Number(group.basic_total || 0),
          Number(group.markup_total || 0),
          Number(group.proposal_total || 0),
          markupPercent,
        ]);
        this.styleTableDataRow(row, index % 2 === 1, [2, 3, 4, 5]);
        [2, 3, 4].forEach((cellIndex) => this.applyCurrencyCell(row.getCell(cellIndex)));
        row.getCell(5).numFmt = '0.00%';
        row.getCell(5).alignment = { horizontal: 'right', vertical: 'middle' };
      });

      const totalRow = worksheet.addRow([
        'Grand Total',
        Number(this.calculatedTotals.basic_cost_total || 0),
        Number(this.calculatedTotals.total_markup_amount || 0),
        Number(this.calculatedTotals.grand_total || 0),
        Number(this.calculatedTotals.basic_cost_total || 0) > 0
          ? Number(this.calculatedTotals.total_markup_amount || 0) / Number(this.calculatedTotals.basic_cost_total || 0)
          : 0,
      ]);
      this.styleGrandTotalRow(totalRow, [2, 3, 4, 5]);
      [2, 3, 4].forEach((cellIndex) => this.applyCurrencyCell(totalRow.getCell(cellIndex)));
      totalRow.getCell(5).numFmt = '0.00%';
      totalRow.getCell(5).alignment = { horizontal: 'right', vertical: 'middle' };

      this.applyOuterBorder(worksheet, headerRow.number, totalRow.number, 1, 5);
    },
    addSheetTitle(worksheet, title, subtitle, totalColumns) {
      worksheet.mergeCells(1, 1, 1, totalColumns);
      worksheet.mergeCells(2, 1, 2, totalColumns);
      const titleCell = worksheet.getCell(1, 1);
      const subtitleCell = worksheet.getCell(2, 1);
      titleCell.value = title;
      subtitleCell.value = subtitle;
      titleCell.font = { size: 16, bold: true, color: { argb: 'FFFFFFFF' } };
      subtitleCell.font = { size: 11, color: { argb: 'FFDCE6F2' } };
      titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
      subtitleCell.alignment = { horizontal: 'center', vertical: 'middle' };
      titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E78' } };
      subtitleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF2F75B5' } };
      worksheet.getRow(1).height = 24;
      worksheet.getRow(2).height = 20;
    },
    addSectionHeader(worksheet, rowIndex, title, totalColumns = 2) {
      worksheet.mergeCells(rowIndex, 1, rowIndex, totalColumns);
      const cell = worksheet.getCell(rowIndex, 1);
      cell.value = title;
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 11 };
      cell.alignment = { horizontal: 'left', vertical: 'middle' };
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF5B9BD5' } };
      cell.border = this.fullBorder('FF9CC2E5');
      worksheet.getRow(rowIndex).height = 18;
    },
    addKeyValueSection(worksheet, startRow, title, rows, options = {}, mergeColumns = 2) {
      this.addSectionHeader(worksheet, startRow, title, mergeColumns);
      let rowIndex = startRow + 1;
      rows.forEach((entry, entryIndex) => {
        const row = worksheet.getRow(rowIndex);
        row.getCell(1).value = entry[0];
        row.getCell(2).value = entry[1];
        row.getCell(1).font = { bold: true, color: { argb: 'FF1F1F1F' } };
        row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEAF2F8' } };
        row.getCell(2).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: entryIndex % 2 === 0 ? 'FFFFFFFF' : 'FFF8FBFF' } };
        row.getCell(1).border = this.fullBorder('FFD9E2F3');
        row.getCell(2).border = this.fullBorder('FFD9E2F3');
        row.getCell(1).alignment = { vertical: 'middle' };
        row.getCell(2).alignment = { vertical: 'middle', wrapText: true };
        if (options.currencyValueRows && options.currencyValueRows.includes(entryIndex + 1)) {
          this.applyCurrencyCell(row.getCell(2));
        }
        if (options.emphasizeLastRow && entryIndex === rows.length - 1) {
          row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9EAD3' } };
          row.getCell(2).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2F0D9' } };
          row.font = { bold: true };
        }
        rowIndex += 1;
      });
      return rowIndex;
    },
    styleTableHeaderRow(row) {
      row.height = 20;
      row.eachCell((cell) => {
        cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF4472C4' } };
        cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
        cell.border = this.fullBorder('FFB4C6E7');
      });
    },
    styleTableDataRow(row, shaded, rightAlignedColumns = []) {
      row.eachCell((cell, colNumber) => {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: shaded ? 'FFF7FBFF' : 'FFFFFFFF' },
        };
        cell.border = this.fullBorder('FFE3EAF3');
        cell.alignment = {
          horizontal: rightAlignedColumns.includes(colNumber) ? 'right' : 'left',
          vertical: 'middle',
          wrapText: true,
        };
      });
      row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    },
    styleSubtotalRow(row, rightAlignedColumns = []) {
      row.eachCell((cell, colNumber) => {
        cell.border = this.fullBorder('FFBDD7EE');
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFDDEBF7' } };
        cell.font = { bold: true, color: { argb: 'FF1F1F1F' } };
        cell.alignment = {
          horizontal: rightAlignedColumns.includes(colNumber) ? 'right' : 'left',
          vertical: 'middle',
        };
      });
    },
    styleGrandTotalRow(row, rightAlignedColumns = []) {
      row.eachCell((cell, colNumber) => {
        cell.border = this.fullBorder('FFA9D18E');
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2F0D9' } };
        cell.font = { bold: true, size: 11, color: { argb: 'FF274E13' } };
        cell.alignment = {
          horizontal: rightAlignedColumns.includes(colNumber) ? 'right' : 'left',
          vertical: 'middle',
        };
      });
    },
    applyCurrencyCell(cell) {
      cell.numFmt = '"Rp" #,##0.00';
      cell.alignment = { horizontal: 'right', vertical: 'middle' };
    },
    applyOuterBorder(worksheet, startRow, endRow, startCol, endCol) {
      for (let rowIndex = startRow; rowIndex <= endRow; rowIndex += 1) {
        for (let colIndex = startCol; colIndex <= endCol; colIndex += 1) {
          const cell = worksheet.getCell(rowIndex, colIndex);
          const currentBorder = cell.border || {};
          cell.border = {
            top: rowIndex === startRow ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.top,
            left: colIndex === startCol ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.left,
            bottom: rowIndex === endRow ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.bottom,
            right: colIndex === endCol ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.right,
          };
        }
      }
    },
    fullBorder(color = 'FFD9E2F3') {
      return {
        top: { style: 'thin', color: { argb: color } },
        left: { style: 'thin', color: { argb: color } },
        bottom: { style: 'thin', color: { argb: color } },
        right: { style: 'thin', color: { argb: color } },
      };
    },
    downloadExcelFile(buffer, filename) {
      const blob = new Blob(
        [buffer],
        { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
      );
      const link = document.createElement('a');
      link.href = window.URL.createObjectURL(blob);
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => {
        window.URL.revokeObjectURL(link.href);
      }, 1000);
    },
    formatHistoryAction(action) {
      const labels = {
        created: 'Dokumen Dibuat',
        updated: 'Dokumen Diperbarui',
        submitted: 'Submit for Review',
        revision_requested: 'Request Revision',
        approved: 'Approved',
        issued: 'Marked as Issued',
      };

      return labels[action] || action;
    },
    submitForReview() {
      axios.post(`/cost-proposals/${this.$route.params.id}/submit`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'Cost proposal berhasil dikirim untuk review.', { type: 'success' });
          this.loadDocument();
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal mengirim cost proposal untuk review.');
        });
    },
    requestRevision() {
      if (!this.approvalActionNote.trim()) {
        this.errorMessage = 'Catatan revisi wajib diisi.';
        return;
      }

      axios.post(`/cost-proposals/${this.$route.params.id}/request-revision`, {
        note: this.approvalActionNote.trim(),
      }).then((response) => {
        this.approvalActionNote = '';
        this.$toasted.show(response.data.message || 'Cost proposal berhasil dikembalikan untuk revisi.', { type: 'success' });
        this.loadDocument();
      }).catch((error) => {
        this.errorMessage = this.getErrorMessage(error, 'Gagal meminta revisi cost proposal.');
      });
    },
    approveDocument() {
      axios.post(`/cost-proposals/${this.$route.params.id}/approve`, {
        note: this.approvalActionNote.trim(),
      }).then((response) => {
        this.approvalActionNote = '';
        this.$toasted.show(response.data.message || 'Cost proposal berhasil disetujui.', { type: 'success' });
        this.loadDocument();
      }).catch((error) => {
        this.errorMessage = this.getErrorMessage(error, 'Gagal menyetujui cost proposal.');
      });
    },
    issueDocument() {
      axios.post(`/cost-proposals/${this.$route.params.id}/issue`, {
        note: this.approvalActionNote.trim(),
      }).then((response) => {
        this.approvalActionNote = '';
        this.$toasted.show(response.data.message || 'Cost proposal berhasil ditandai sebagai issued.', { type: 'success' });
        this.loadDocument();
      }).catch((error) => {
        this.errorMessage = this.getErrorMessage(error, 'Gagal menandai proposal sebagai issued.');
      });
    },
    getPayload() {
      return {
        ...this.form,
        basic_cost_id: Number(this.form.basic_cost_id),
        signatory_id: Number(this.form.signatory_id),
        items: this.lineItems.map((item, index) => ({
          basic_cost_item_id: item.basic_cost_item_id,
          category_name: item.category_name,
          subcategory_name: item.subcategory_name,
          item_name: item.item_name,
          unit_symbol: item.unit_symbol,
          quantity: Number(item.quantity || 0),
          duration: Number(item.duration || 0),
          basic_unit_price: Number(item.basic_unit_price || 0),
          basic_total_price: Number(item.basic_total_price || 0),
          markup_type: item.markup_type || 'percent',
          markup_value: Number(item.markup_value || 0),
          sort_order: item.sort_order != null ? Number(item.sort_order) : index,
        })),
      };
    },
    submitForm() {
      this.errorMessage = '';
      if (this.isEditMode && !this.canEditDocument) {
        this.errorMessage = 'Dokumen ini tidak bisa diedit pada status saat ini.';
        return;
      }
      if (!this.form.basic_cost_id) {
        this.errorMessage = 'Pilih basic cost approved terlebih dahulu.';
        return;
      }
      if (!this.form.signatory_id) {
        this.errorMessage = 'Pilih penandatangan dokumen terlebih dahulu.';
        return;
      }

      this.isSubmitting = true;
      const request = this.isEditMode
        ? axios.put(`/cost-proposals/${this.$route.params.id}`, this.getPayload())
        : axios.post('/cost-proposals', this.getPayload());

      request.then((response) => {
        this.$toasted.show(response.data.message || 'Cost proposal berhasil disimpan.', { type: 'success' });
        this.$router.push('/app/cost-proposals');
      }).catch((error) => {
        if (error && error.response && error.response.data && error.response.data.errors) {
          const firstErrorKey = Object.keys(error.response.data.errors)[0];
          this.errorMessage = error.response.data.errors[firstErrorKey];
          return;
        }
        this.errorMessage = this.getErrorMessage(error, 'Gagal menyimpan cost proposal.');
      }).finally(() => {
        this.isSubmitting = false;
      });
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
  },
  created() {
    Promise.all([
      this.loadApprovedBasicCosts(),
      this.loadSignatories(),
    ]).then(() => {
      this.loadDocument();
    });
  },
};
</script>

<style src="./CostProposal.scss" lang="scss" scoped />
