<template>
  <div class="public-quotation-page">
    <div v-if="isLoading" class="public-quotation-state">
      Memuat dokumen quotation...
    </div>
    <div v-else-if="errorMessage" class="public-quotation-state error">
      {{ errorMessage }}
    </div>
    <div v-else class="quotation-sheet">
      <div class="quotation-header">
        <img :src="logoImage" alt="ESER Geosurvey" class="quotation-logo">
        <div class="quotation-company">
          <div class="quotation-company-name">PT. Eser Geosurvey Indonesia</div>
          <div class="quotation-company-meta">
            Menara Apartemen Edelweiss, Apartemen Rajawali, Town House No.20 - Jl. Rajawali Selatan I No.63 6,
            RT.6/RW.2, Gn. Sahari Utara, Kecamatan Sawah Besar, Kota Jakarta Pusat
          </div>
          <div class="quotation-company-meta">office@esergeosurvey.com</div>
        </div>
      </div>

      <div class="quotation-line" />

      <div class="quotation-meta">
        <div class="quotation-meta-left">
          <div><strong>No.</strong> : {{ document.proposal_number || '-' }}</div>
          <div><strong>Perihal</strong> : {{ document.proposal_subject || defaultSubject }}</div>
          <div><strong>Lampiran</strong> : {{ document.attachment_label || 'Rincian Anggaran Biaya' }}</div>
        </div>
        <div class="quotation-meta-right">
          Jakarta, {{ formatDateDisplay(document.proposal_date) }}
        </div>
      </div>

      <div class="quotation-body">
        <p>Kepada,</p>
        <p><strong>Yth. {{ document.attention_name || document.client_name || '-' }}</strong></p>

        <p>
          Berdasarkan kebutuhan penawaran untuk pekerjaan {{ document.proposal_subject || defaultSubject }},
          maka kami sampaikan penawaran kami senilai:
        </p>

        <div class="quotation-amount">Rp. {{ formatCurrencyPlain(document.grand_total) }}</div>
        <div class="quotation-terbilang">
          Terbilang : #{{ terbilang(document.grand_total) }} Rupiah#
        </div>

        <p>
          Demikian kami sampaikan, apabila terdapat hal yang perlu didiskusikan, kami siap untuk berdiskusi lebih lanjut.
        </p>
        <p>
          Terima kasih atas perhatian dan kepercayaan yang diberikan. Kami berharap dapat menyelesaikan pekerjaan ini dengan baik dan memuaskan.
        </p>
      </div>

      <div v-if="quotationRows.length > 0" class="quotation-table-wrapper">
        <table class="quotation-table">
          <thead>
            <tr>
              <th>No.</th>
              <th>Description</th>
              <th>Unit</th>
              <th>Qty</th>
              <th>Duration</th>
              <th>Unit Price</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in quotationRows" :key="row.key" :class="row.type === 'subtotal' ? 'subtotal-row' : ''">
              <td v-if="row.type === 'item'">{{ row.no }}</td>
              <td v-else></td>
              <td v-if="row.type === 'item'">{{ row.description }}</td>
              <td v-else class="subtotal-label" colspan="5">{{ row.label }}</td>
              <td v-if="row.type === 'item'" class="text-center">{{ row.unit }}</td>
              <td v-if="row.type === 'item'" class="text-end">{{ row.quantity }}</td>
              <td v-if="row.type === 'item'" class="text-end">{{ row.duration }}</td>
              <td v-if="row.type === 'item'" class="text-end">{{ row.unitPrice }}</td>
              <td class="text-end">{{ row.total }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td></td>
              <td class="grand-total-label" colspan="5">GRAND TOTAL</td>
              <td class="grand-total-value text-end">{{ formatCurrencyPlain(document.grand_total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="quotation-signature">
        <div class="signature-qr" v-if="qrDataUrl">
          <img :src="qrDataUrl" alt="QR Dokumen">
          <small>Scan untuk membuka dokumen proposal</small>
        </div>
        <div class="signature-block">
          <div>Hormat kami,</div>
          <div class="signature-company">PT. Eser Geosurvey Indonesia</div>
          <div class="signature-image-wrap signature-image-stack">
            <img :src="signSealImage" alt="Logo ESER" class="signature-seal-image">
            <img v-if="document.signatory_signature_url" :src="document.signatory_signature_url" alt="Tanda tangan" class="signature-image">
          </div>
          <div v-if="!document.signatory_signature_url" class="signature-space" />
          <div class="signature-name">{{ document.signatory_name || '-' }}</div>
          <div>{{ document.signatory_title || '-' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import QRCode from 'qrcode';
import logoImage from '@/assets/company/eser-geosurvey-logo.png';
import signSealImage from '@/assets/company/eser_geosurvey-transparent.png';

export default {
  name: 'PublicQuotationPage',
  data() {
    return {
      logoImage,
      signSealImage,
      document: null,
      isLoading: false,
      errorMessage: '',
      qrDataUrl: '',
    };
  },
  computed: {
    defaultSubject() {
      return this.document && this.document.project_name
        ? `Penawaran ${this.document.project_name}`
        : 'Penawaran Biaya';
    },
    quotationRows() {
      if (!this.document || !Array.isArray(this.document.items)) {
        return [];
      }

      const groups = this.document.items.reduce((acc, item) => {
        const key = item.category_name || '-';
        if (!acc[key]) {
          acc[key] = [];
        }
        acc[key].push(item);
        return acc;
      }, {});

      const rows = [];
      let runningNumber = 1;

      Object.keys(groups).forEach((categoryName) => {
        let subtotal = 0;
        groups[categoryName].forEach((item) => {
          const proposalTotal = Number(item.proposal_total_price != null ? item.proposal_total_price : item.total_price || 0);
          const proposalUnitPrice = Number(item.proposal_unit_price != null ? item.proposal_unit_price : item.unit_price || 0);
          subtotal += proposalTotal;
          rows.push({
            key: `item-${item.id || runningNumber}`,
            type: 'item',
            no: runningNumber,
            description: item.item_name || '-',
            unit: item.unit_symbol || '-',
            quantity: this.formatNumber(item.quantity),
            duration: this.formatNumber(item.duration),
            unitPrice: this.formatCurrencyPlain(proposalUnitPrice),
            total: this.formatCurrencyPlain(proposalTotal),
          });
          runningNumber += 1;
        });

        rows.push({
          key: `subtotal-${categoryName}`,
          type: 'subtotal',
          label: `Subtotal ${categoryName}`,
          total: this.formatCurrencyPlain(subtotal),
        });
      });

      return rows;
    },
  },
  methods: {
    loadDocument() {
      this.isLoading = true;
      this.errorMessage = '';

      axios.get(`/public/cost-proposals/${this.$route.params.token}`)
        .then(async (response) => {
          this.document = response.data.data || null;
          await this.buildQr();
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Dokumen quotation tidak ditemukan.';
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    async buildQr() {
      if (!this.document) {
        return;
      }

      this.qrDataUrl = await QRCode.toDataURL(this.currentDocumentUrl(), {
        margin: 1,
        width: 120,
        color: {
          dark: '#1F4E78',
          light: '#FFFFFF',
        },
      });
    },
    currentDocumentUrl() {
      return `${window.location.origin}${window.location.pathname}#/quotation/${this.$route.params.token}`;
    },
    formatCurrencyPlain(value) {
      return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
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
  },
  created() {
    this.loadDocument();
  },
};
</script>

<style lang="scss" scoped>
.public-quotation-page {
  min-height: 100vh;
  background: #eef3f8;
  padding: 24px;
}

.public-quotation-state {
  max-width: 960px;
  margin: 40px auto;
  background: #fff;
  padding: 40px;
  border-radius: 16px;
  color: #1f1f1f;
  text-align: center;
}

.public-quotation-state.error {
  color: #b42318;
}

.quotation-sheet {
  max-width: 960px;
  margin: 0 auto;
  background: #fff;
  padding: 32px 36px 48px;
  border-radius: 18px;
  box-shadow: 0 18px 50px rgba(15, 32, 57, 0.08);
  color: #1f1f1f;
}

.quotation-header {
  display: flex;
  gap: 18px;
  align-items: center;
}

.quotation-logo {
  width: 180px;
  max-width: 100%;
}

.quotation-company-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #133c66;
}

.quotation-company-meta {
  font-size: 0.9rem;
  color: #4b5565;
  line-height: 1.45;
}

.quotation-line {
  height: 2px;
  background: #295f98;
  margin: 18px 0 22px;
}

.quotation-meta {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 28px;
  font-size: 0.98rem;
}

.quotation-meta-left > div {
  margin-bottom: 6px;
}

.quotation-meta-right {
  font-weight: 600;
  color: #8b5a13;
  white-space: nowrap;
}

.quotation-body {
  font-size: 1rem;
  line-height: 1.8;
}

.quotation-amount {
  font-size: 2rem;
  font-weight: 800;
  color: #0e2d52;
  text-align: center;
  margin: 20px 0 10px;
}

.quotation-terbilang {
  font-size: 1.05rem;
  font-weight: 700;
  font-style: italic;
  text-align: center;
  margin-bottom: 24px;
}

.quotation-signature {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 24px;
  margin-top: 32px;
}

.quotation-table-wrapper {
  margin-top: 28px;
}

.quotation-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.94rem;
}

.quotation-table th,
.quotation-table td {
  border: 1px solid #c9d7e6;
  padding: 10px 12px;
  vertical-align: middle;
}

.quotation-table thead th {
  background: #1f4e78;
  color: #fff;
  font-weight: 700;
  text-align: center;
}

.quotation-table tbody tr:nth-child(even) td {
  background: #f8fbff;
}

.quotation-table .subtotal-row td {
  background: #eaf2f8;
  font-weight: 700;
}

.quotation-table tfoot td {
  background: #d9ead3;
  color: #1f4e78;
  font-weight: 800;
}

.subtotal-label,
.grand-total-label {
  text-align: right;
}

.grand-total-value {
  font-size: 1rem;
}

.text-end {
  text-align: right;
}

.text-center {
  text-align: center;
}

.signature-qr {
  text-align: center;
}

.signature-qr img {
  width: 108px;
  height: 108px;
  object-fit: contain;
  display: block;
  margin: 0 auto 6px;
}

.signature-qr small {
  color: #51657d;
}

.signature-block {
  min-width: 260px;
}

.signature-company {
  margin: 2px 0 10px;
}

.signature-image-wrap {
  min-height: 84px;
  margin-bottom: 4px;
}

.signature-image-stack {
  position: relative;
  width: 180px;
}

.signature-seal-image {
  position: absolute;
  left: 18px;
  top: -2px;
  width: 122px;
  max-width: 122px;
  object-fit: contain;
  opacity: 0.65;
  pointer-events: none;
}

.signature-image {
  display: block;
  max-width: 180px;
  max-height: 84px;
  object-fit: contain;
  position: relative;
  z-index: 1;
}

.signature-space {
  height: 84px;
}

.signature-name {
  font-weight: 700;
  text-decoration: underline;
}

@media (max-width: 768px) {
  .public-quotation-page {
    padding: 12px;
  }

  .quotation-sheet {
    padding: 24px 20px 32px;
  }

  .quotation-header,
  .quotation-meta,
  .quotation-signature {
    flex-direction: column;
    align-items: flex-start;
  }

  .quotation-logo {
    width: 150px;
  }

  .quotation-amount {
    font-size: 1.5rem;
  }
}
</style>
