<script setup>
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { formatDate } from "@/plugins/date/dateUtils";
import { paginationMeta, scrollTop } from "@/plugins/dashboards/utils";
import { ref} from "vue";
import axiosIns from "@axios";
import { useSnackBarStore } from "@core/utils/snackBar";
import { savingAttachment } from "@core/utils/tableHeaders";
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { watchDebounced } from '@vueuse/core'
import { getFormattedCustomFieldValue } from "@/plugins/common";
import { useI18n } from "vue-i18n";

const I18n = useI18n();
const snackBarStore = useSnackBarStore();
const savingStore = useSavingsStore();
const savingAttachments = ref([]);
const props = defineProps({
  savingData: {
    type: Object,
    required: true,
  },
});
const isAddDocumentDialogVisible = ref(false)
const totalPage = ref(0);
const searchQuery = ref('')
const tableLoader = ref(false);
const customFields = ref([]);
const isCustomFieldLoaded = ref(true);

const totalDocument = ref(0)
const options = ref({
  itemsPerPage: 10,
  page: 1,
});
watchDebounced(() => ({
  currentPage: options.value.page,
  itemsPerPage: options.value.itemsPerPage,
  searchQuery: searchQuery.value,
}),
  (v) => {
    fetchSavingAttachment();
  },
  { debounce: 400},
)

const fetchSavingAttachment = () => { 

  isCustomFieldLoaded.value = false;

  savingStore.fetchOneSavingAttachment({
    saving_id:props.savingData.id,
    options: options.value,
    search: searchQuery.value,
  })
  .then((response) => {
    savingAttachments.value = response.data.attachmentData;
    totalDocument.value = response.data.total;
    options.value.page = +response.data.page;
    totalPage.value = response.data.totalPage;
    customFields.value = response.data?.custom_fields || [];
  })
  .catch((error) => {
    console.log(error);
  })
  .finally(() => {
    isCustomFieldLoaded.value = true;
  });
};



const savingsDocumentTableModifiedHeaders = computed(() => {
  const tempHeader = [...savingAttachment];
  
  customFields.value.forEach(item => {
    tempHeader.push({
      title: item.field_name,
      key: customHeaderKey(item),
      sortable: false,
      visible: false,
      fixed: false
    });
  })

  // Filter out the actions object
  const filteredArr = tempHeader.filter(item => item.key != "action");

  // Find the actions object
  const actionsObj = tempHeader.find(item => item.key == "action");

  // Append the actions object at the end if it exists
  if (actionsObj) {
    filteredArr.push(actionsObj);
  }

  return filteredArr;
});

const savingAttachmentHeader = ref(structuredClone(toRaw(savingsDocumentTableModifiedHeaders.value.filter(item => item.visible))));

const savingAttachmentHeaderTrans = computed(() => {
  const tempHeader = [];

  savingAttachmentHeader.value.forEach(item => {
    tempHeader.push({
      title: I18n.t(item.title),
      key: item.key,
      sortable: item.sortable,
      visible: item.visible,
      fixed: item.fixed
    });
  })

  return tempHeader;
})

const customHeaderKey = (header) => {
  return header?.field_name?.split(' ')?.join('_')+'_'+header?.id;
}

//---------------------------------Add Form Document---------------------------------------//

const openAddDocumentDialog = () => {
  scrollTop()
  .then(() => {
    isAddDocumentDialogVisible.value = true;
  });
}

//----------------------------------- Download Document -----------------------------------//

const getFileName = (fileName) => {

  tableLoader.value = true;

  savingStore.downloadDocument({
    filename: fileName,
  })
  .then((response) => {
    const url = window.URL.createObjectURL(new Blob([response.data]));
    // Create a link element
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', fileName); // Use the actual filename
    // Append the link to the body
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url); // Release the object URL
    const message = "Download successful!";
    snackBarStore.message = message;
    snackBarStore.type = "success";

  })
  .catch((error) => {
   console.error("Error downloading the file:", error);
   snackBarStore.message = "Error approved item";
   snackBarStore.type = "error";
  })
  .finally(() => {
    snackBarStore.snackbar = true;

    tableLoader.value = false;
  });

};

const deleteResponse = ref(null);
const deleteDialog = ref(false)
const selectedDocument = ref('');

const deleteDocument = (card) => {
  selectedDocument.value = card;
  deleteDialog.value = true;
}

const confirmDeleteSupplierContact = (val) => {
  
  if(val){
    docDeleteApi(selectedDocument.value)
  }
}

const docDeleteApi = (itemId) => { 
  savingStore.deleteOneDocument({
    id: itemId,
  })
  .then((response) => {
    deleteResponse.value = response;
    snackBarStore.message = response.data.message;
    snackBarStore.type = "success";
    fetchSavingAttachment();
  })
  .catch((error) => {
    console.error("Error deleting item:", error);
    snackBarStore.message = "Error deleting item";
    snackBarStore.type = "error";
  })
  .finally(() => {
    snackBarStore.snackbar = true;
  });
};
const addAttachment=()=>{
  fetchSavingAttachment();
};

const selectedHeaders = (headers) => {
  savingAttachmentHeader.value = headers;
  const temp = savingAttachments.value;
  savingAttachments.value = [];
  savingAttachments.value = temp;
};
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :title="$t('Savings Attachments')">
        <VCardText class="d-flex flex-wrap py-4 gap-4">
          <div class="d-flex gap-3">
            <AppSelect
              :model-value="options.itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 20, title: '20' },
                { value: 30, title: '30' },
              ]"
              style="width: 6.25rem;"
              @update:model-value="options.itemsPerPage = parseInt($event, 10), options.page = 1"
            />
            <ColumnChooser
              v-if="isCustomFieldLoaded"
              :headers="savingsDocumentTableModifiedHeaders"
              @selected-headers="selectedHeaders"
            />
          </div>

          <VSpacer />

          <div class="d-flex gap-3">  
            <div style="inline-size: 15rem">
              <AppTextField
                v-model="searchQuery"
                :placeholder="$t('Search')"
                class="pr-2"
              />
            </div>          
  
            <VBtn
              v-if="$can('create', 'savings') ||  $can('edit', 'savings')"
              style="width: 14.25rem"
              prepend-icon="tabler-plus"
             @click="openAddDocumentDialog"
            >
              {{ $t('Add Attachment') }}
            </VBtn>
          </div>

        </VCardText>

        <VDivider />
        <!-- VDataTable -->
        <VDataTableServer
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :headers="savingAttachmentHeaderTrans"
          :items="savingAttachments"
          :items-length="totalDocument"
          :loading="tableLoader"
          @update:options="options = $event"
        >

          <!-- no data text -->
          <template #no-data>
            {{ $t('No data available') }}
          </template>


          <template v-for="(header) in customFields" v-slot:[`item.${customHeaderKey(header)}`]="{ item }">
            <div class="d-flex align-center" :key="`${customHeaderKey(header)}`">
              {{ getFormattedCustomFieldValue(item.raw.custom_fields, header) }}
            </div>
          </template>

          <template #item.action="{ item }">
            <VBtn icon variant="text" size="small" color="medium-emphasis" :disabled="tableLoader">
              <VIcon size="24" icon="tabler-dots-vertical" />
              <VMenu activator="parent">
                <VList>
                  <VListItem
                    @click="getFileName(item.raw.document?.file_name)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-download" />
                    </template>
                    <VListItemTitle> {{ $t('Download') }} </VListItemTitle>
                  </VListItem>

                  <VListItem
                    v-if="$can('archive', 'savings')"
                    icon
                    variant="text"
                    size="small"
                    color="medium-emphasis"
                    @click="deleteDocument(item.raw.id)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-trash" />
                    </template>
                    <VListItemTitle>{{ $t('Delete') }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </VBtn>
          </template>

          <!-- Pagination -->
           <template #bottom>
            <VDivider />
            <div
              class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3"
            >
              <p class="text-sm text-disabled mb-0">
                 {{ paginationMeta(options, totalDocument) }}
              </p>
            <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalDocument / options.itemsPerPage)"
                  :total-visible="$vuetify.display.smAndDown ? 3 : 5"
                >
                <template #prev="slotProps">
                  <VBtn
                    variant="tonal"
                    color="default"
                    v-bind="slotProps"
                    :icon="false"
                  >
                    {{ $t('Previous') }}
                  </VBtn>
                </template>
                <template #next="slotProps">
                  <VBtn
                    variant="tonal"
                    color="default"
                    v-bind="slotProps"
                    :icon="false"
                  >
                    {{ $t('Next') }}
                  </VBtn>
                </template>
              </VPagination>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </VCol>
  </VRow>

  <AddSavingAttachmentDialog 
    v-model:is-dialog-visible="isAddDocumentDialogVisible"
    :saving-data="props.savingData"
    :custom-fields="customFields"
    @response="addAttachment"
  />

  <SimpleConfirmDialog
    v-model:isDialogVisible="deleteDialog"
    @click-ok="confirmDeleteSupplierContact"
    title="Delete Attachment"
    sub-title="Are you sure you want to delete this document ?"
    ok-btn-text="Yes"
  />
</template>

<style lang="scss" >
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
