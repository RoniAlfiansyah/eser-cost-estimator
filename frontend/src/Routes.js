import Vue from 'vue';
import Router from 'vue-router';

import Layout from '@/components/Layout/Layout';
// Core
import TypographyPage from '@/pages/Core/Typography/Typography';
import ColorsPage from '@/pages/Core/Colors/Colors';
import CoreGridPage from '@/pages/Core/Grid/Grid';
// Tables
import TablesBasicPage from '@/pages/Tables/Basic/Basic';
import TablesDynamicPage from '@/pages/Tables/Dynamic/Dynamic';
// Maps
import GoogleMapPage from '@/pages/Maps/Google/Google';
import AmchartsMapPage from '@/pages/Maps/Amcharts/Amcharts';
// Main
import VisitsPage from '@/pages/Visits/Visits';
import AnalyticsPage from '@/pages/Dashboard/Dashboard';
import WidgetsPage from '@/pages/Widgets/Widgets';
// Charts
import ChartsPage from '@/pages/Charts/Charts';
import ApexPage from '@/pages/Charts/Apex/Apex';
import EchartsPage from '@/pages/Charts/Echarts/Echarts';
import HighchartsPage from '@/pages/Charts/Highcharts/Highcharts';

import ProfilePage from '@/pages/Profile/Profile';
// Profile
import PackagePage from '@/pages/Package/Package';
// Email
import EmailPage from '@/pages/Email/Email';
// Ecommerce
import ProductsManagement from '@/pages/Ecommerce/Management/Management';
import ProductEdit from '@/pages/Ecommerce/Management/components/ProductEdit/ProductEdit';
import ProductsPage from '@/pages/Ecommerce/ProductsGrid/ProductsGrid';
import ProductPage from '@/pages/Ecommerce/ProductPage/ProductPage';
// Grid
import GridPagePage from '@/pages/Grid/Grid';
// Forms
import FormElementsPage from '@/pages/Forms/Elements/Elements';
import FormValidationPage from '@/pages/Forms/Validation/Validation';
import FormWizardPage from '@/pages/Forms/Wizard/Wizard';
// Extra
import CalendarPage from '@/pages/Extra/Calendar/Calendar';
import InvoicePage from '@/pages/Extra/Invoice/Invoice';
import SearchPage from '@/pages/Extra/Search/Search';
import TimelinePage from '@/pages/Extra/Timeline/Timeline';
import GalleryPage from '@/pages/Extra/Gallery/Gallery';
import Login from '@/pages/Login/Login';
import LoginDemo from '@/pages/Extra/LoginDemo/LoginDemo';
import Register from '@/pages/Register/Register';
import ErrorPage from '@/pages/Error/Error';
// Ui
import AlertsPage from '@/pages/Ui/Alerts/Alerts';
import BadgePage from '@/pages/Ui/Badge/Badge';
import ButtonsPage from '@/pages/Ui/Buttons/Buttons';
import CardPage from '@/pages/Ui/Card/Card';
import CarouselPage from '@/pages/Ui/Carousel/Carousel';
import IconsPage from '@/pages/Ui/Icons/Icons';
import JumbotronPage from '@/pages/Ui/Jumbotron/Jumbotron';
import ListGroupsPage from '@/pages/Ui/ListGroups/ListGroups';
import ModalPage from '@/pages/Ui/Modal/Modal';
import NavPage from '@/pages/Ui/Nav/Nav';
import NavbarPage from '@/pages/Ui/Navbar/Navbar';
import NotificationsPage from '@/pages/Ui/Notifications/Notifications';
import PopoversPage from '@/pages/Ui/Popovers/Popovers';
import ProgressPage from '@/pages/Ui/Progress/Progress';
import TabsPage from '@/pages/Ui/Tabs/Tabs';
import AdminUsersListPage from '@/pages/Admin/Users/List';
import AdminUsersFormPage from '@/pages/Admin/Users/Form';
import MasterDataListPage from '@/pages/Admin/MasterData/List';
import MasterDataFormPage from '@/pages/Admin/MasterData/Form';
import BasicCostListPage from '@/pages/BasicCost/List';
import BasicCostFormPage from '@/pages/BasicCost/Form';
import CostProposalListPage from '@/pages/CostProposal/List';
import CostProposalFormPage from '@/pages/CostProposal/Form';
import PublicQuotationPage from '@/pages/CostProposal/PublicQuotation';
import TemplateListPage from '@/pages/Templates/List';
import TemplateFormPage from '@/pages/Templates/Form';
import SchedulerPage from '@/pages/Scheduler/Scheduler';

import Documentation from '@/documentation/DocumentationLayout';
import DocLicensesPage from '@/documentation/pages/getting-started/Licenses';
import DocQuickStartPage from '@/documentation/pages/getting-started/QuickStart';
import DocOverviewPage from '@/documentation/pages/getting-started/Overview';
import DocLibsPage from '@/documentation/pages/Libs';
import DocPagesPage from '@/documentation/pages/Pages';
import DocAlertsPage from '@/documentation/pages/components/Alerts';
import DocBadgePage from '@/documentation/pages/components/Badge';
import DocButtonsPage from '@/documentation/pages/components/Buttons';
import DocCardPage from '@/documentation/pages/components/Card';
import DocCarouselPage from '@/documentation/pages/components/Carousel';
import DocModalPage from '@/documentation/pages/components/Modal';
import DocNavPage from '@/documentation/pages/components/Nav';
import DocNavbarPage from '@/documentation/pages/components/Navbar';
import DocPopoversPage from '@/documentation/pages/components/Popovers';
import DocProgressPage from '@/documentation/pages/components/Progress';
import DocTabsPage from '@/documentation/pages/components/Tabs';

import { getCurrentUser, isAdmin, isAuthenticated } from './mixins/auth';

Vue.use(Router);

const router = new Router({
  routes: [
    {path: '/', redirect: '/app/main/analytics'},
    {path: '/app', redirect: '/app/main/analytics'},
    {path: '/documentation', redirect: '/documentation/getting-started/overview'},
    {
      path: '/login',
      name: 'Login',
      component: Login,
    },
    {
      path: '/login-demo',
      name: 'LoginDemo',
      component: LoginDemo,
    },
    {
      path: '/register',
      name: 'Register',
      component: Register,
    },
    {
      path: '/error',
      name: 'Error',
      component: ErrorPage,
    },
    {
      path: '/quotation/:token',
      name: 'PublicQuotationPage',
      component: PublicQuotationPage,
    },
    {
      path: '/documentation',
      name: 'Documentation',
      component: Documentation,
      children: [
        {
          path: 'getting-started/overview',
          name: 'Overview',
          component: DocOverviewPage,
        },
        {
          path: 'getting-started/licenses',
          name: 'Licenses',
          component: DocLicensesPage,
        },
        {
          path: 'getting-started/quick-start',
          name: 'QuickStart',
          component: DocQuickStartPage,
        },
        {
          path: 'components/alerts',
          name: 'Alerts',
          component: DocAlertsPage,
        },
        {
          path: 'components/badge',
          name: 'Badge',
          component: DocBadgePage,
        },
        {
          path: 'components/buttons',
          name: 'Buttons',
          component: DocButtonsPage,
        },
        {
          path: 'components/card',
          name: 'Card',
          component: DocCardPage,
        },
        {
          path: 'components/carousel',
          name: 'Carousel',
          component: DocCarouselPage,
        },
        {
          path: 'components/modal',
          name: 'Modal',
          component: DocModalPage,
        },
        {
          path: 'components/nav',
          name: 'Nav',
          component: DocNavPage,
        },
        {
          path: 'components/navbar',
          name: 'Navbar',
          component: DocNavbarPage,
        },
        {
          path: 'components/popovers',
          name: 'Popovers',
          component: DocPopoversPage,
        },
        {
          path: 'components/progress',
          name: 'Progress',
          component: DocProgressPage,
        },
        {
          path: 'components/tabs',
          name: 'Tabs',
          component: DocTabsPage,
        },
        {
          path: 'libs',
          name: 'Libs',
          component: DocLibsPage,
        },
        {
          path: 'pages',
          name: 'Pages',
          component: DocPagesPage,
        }
      ]
    },
    {
      path: '/app',
      name: 'Layout',
      component: Layout,
      beforeEnter: async (to, from, next) => {
        isAuthenticated() ? next() : next({path: '/login'});
      },
      children: [
        // main pages
        {
          path: 'main/analytics',
          name: 'AnalyticsPage',
          component: AnalyticsPage,
        },
        {
          path: 'main/visits',
          name: 'VisitsPage',
          component: VisitsPage,
        },
        {
          path: 'main/widgets',
          name: 'WidgetsPage',
          component: WidgetsPage,
        },
        {
          path: 'basic-costs',
          name: 'BasicCostListPage',
          component: BasicCostListPage,
        },
        {
          path: 'scheduler',
          redirect: '/app/scheduler/activities',
        },
        {
          path: 'scheduler/:section',
          name: 'SchedulerPage',
          component: SchedulerPage,
        },
        {
          path: 'basic-costs/create',
          name: 'BasicCostCreatePage',
          component: BasicCostFormPage,
        },
        {
          path: 'basic-costs/:id/edit',
          name: 'BasicCostEditPage',
          component: BasicCostFormPage,
        },
        {
          path: 'cost-proposals',
          name: 'CostProposalListPage',
          component: CostProposalListPage,
        },
        {
          path: 'cost-proposals/create',
          name: 'CostProposalCreatePage',
          component: CostProposalFormPage,
        },
        {
          path: 'cost-proposals/:id/edit',
          name: 'CostProposalEditPage',
          component: CostProposalFormPage,
        },
        // package page
        {
          path: 'package',
          name: 'PackagePage',
          component: PackagePage,
        },
        // profile page
        {
          path: 'profile',
          name: 'ProfilePage',
          component: ProfilePage,
        },
        // email page
        {
          path: 'email',
          name: 'EmailPage',
          component: EmailPage,
        },
        // ecommerce pages
        {
          path: 'ecommerce/management',
          name: 'ProductsManagement',
          component: ProductsManagement,
        },
        {
          path: 'ecommerce/management/:id',
          name: 'ProductEdit',
          component: ProductEdit,
        },
        {
          path: 'ecommerce/management/create',
          name: 'ProductCreate',
          component: ProductEdit,
        },
        {
          path: 'ecommerce/products',
          name: 'ProductsPage',
          component: ProductsPage,
        },
        {
          path: 'ecommerce/product',
          name: 'ProductPage',
          component: ProductPage,
        },
        {
          path: 'ecommerce/product/:id',
          name: 'DefiniteProductPage',
          component: ProductPage,
        },
        // core pages
        {
          path: 'core/typography',
          name: 'TypographyPage',
          component: TypographyPage,
        },
        {
          path: 'core/colors',
          name: 'ColorsPage',
          component: ColorsPage,
        },
        {
          path: 'core/grid',
          name: 'CoreGridPage',
          component: CoreGridPage,
        },
        // ui pages
        {
          path: 'ui/alerts',
          name: 'AlertsPage',
          component: AlertsPage,
        },
        {
          path: 'ui/badge',
          name: 'BadgePage',
          component: BadgePage,
        },
        {
          path: 'ui/buttons',
          name: 'ButtonsPage',
          component: ButtonsPage,
        },
        {
          path: 'ui/card',
          name: 'CardPage',
          component: CardPage,
        },
        {
          path: 'ui/carousel',
          name: 'CarouselPage',
          component: CarouselPage,
        },
        {
          path: 'ui/jumbotron',
          name: 'JumbotronPage',
          component: JumbotronPage,
        },
        {
          path: 'ui/icons',
          name: 'IconsPage',
          component: IconsPage,
        },
        {
          path: 'ui/list-groups',
          name: 'ListGroupsPage',
          component: ListGroupsPage,
        },
        {
          path: 'ui/modal',
          name: 'ModalPage',
          component: ModalPage,
        },
        {
          path: 'ui/nav',
          name: 'NavPage',
          component: NavPage,
        },
        {
          path: 'ui/navbar',
          name: 'NavbarPage',
          component: NavbarPage,
        },
        {
          path: 'ui/notifications',
          name: 'NotificationsPage',
          component: NotificationsPage,
        },
        {
          path: 'ui/popovers',
          name: 'PopoversPage',
          component: PopoversPage,
        },
        {
          path: 'ui/progress',
          name: 'ProgressPage',
          component: ProgressPage,
        },
        {
          path: 'ui/tabs',
          name: 'TabsPage',
          component: TabsPage,
        },
        // forms
        {
          path: 'forms/elements',
          name: 'FormElementsPage',
          component: FormElementsPage,
        },
        {
          path: 'forms/validation',
          name: 'FormValidationPage',
          component: FormValidationPage,
        },
        {
          path: 'forms/wizard',
          name: 'FormWizardPage',
          component: FormWizardPage,
        },
        // Charts
        {
          path: 'charts/overview',
          name: 'ChartsPage',
          component: ChartsPage,
        },
        {
          path: 'charts/apex',
          name: 'ApexPage',
          component: ApexPage,
        },
        {
          path: 'charts/echarts',
          name: 'EchartsPage',
          component: EchartsPage,
        },
        {
          path: 'charts/highcharts',
          name: 'HighchartsPage',
          component: HighchartsPage,
        },
        // grid page
        {
          path: 'grid',
          name: 'GridPage',
          component: GridPagePage,
        },
        // tables pages
        {
          path: 'tables/basic',
          name: 'TablesBasicPage',
          component: TablesBasicPage,
        },
        {
          path: 'tables/dynamic',
          name: 'TablesDynamicPage',
          component: TablesDynamicPage,
        },
        // maps pages
        {
          path: 'maps/google',
          name: 'GoogleMapPage',
          component: GoogleMapPage,
        },
        {
          path: 'maps/amcharts',
          name: 'AmchartsMapPage',
          component: AmchartsMapPage,
        },
        // extra pages
        {
          path: 'extra/calendar',
          name: 'CalendarPage',
          component: CalendarPage,
        },
        {
          path: 'extra/invoice',
          name: 'InvoicePage',
          component: InvoicePage,
        },
        {
          path: 'extra/search',
          name: 'SearchPage',
          component: SearchPage,
        },
        {
          path: 'extra/time-line',
          name: 'TimelinePage',
          component: TimelinePage,
        },
        {
          path: 'extra/gallery',
          name: 'GalleryPage',
          component: GalleryPage,
        },
        {
          path: 'admin/users',
          name: 'AdminUsersListPage',
          component: AdminUsersListPage,
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/users/create',
          name: 'AdminUsersCreatePage',
          component: AdminUsersFormPage,
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/users/:id/edit',
          name: 'AdminUsersEditPage',
          component: AdminUsersFormPage,
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/master-data/categories',
          name: 'AdminCategoriesListPage',
          component: MasterDataListPage,
          meta: { requiresAdmin: true, entityType: 'categories' },
        },
        {
          path: 'admin/master-data/categories/create',
          name: 'AdminCategoriesCreatePage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'categories' },
        },
        {
          path: 'admin/master-data/categories/:id/edit',
          name: 'AdminCategoriesEditPage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'categories' },
        },
        {
          path: 'admin/master-data/subcategories',
          name: 'AdminSubcategoriesListPage',
          component: MasterDataListPage,
          meta: { requiresAdmin: true, entityType: 'subcategories' },
        },
        {
          path: 'admin/master-data/subcategories/create',
          name: 'AdminSubcategoriesCreatePage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'subcategories' },
        },
        {
          path: 'admin/master-data/subcategories/:id/edit',
          name: 'AdminSubcategoriesEditPage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'subcategories' },
        },
        {
          path: 'admin/master-data/units',
          name: 'AdminUnitsListPage',
          component: MasterDataListPage,
          meta: { requiresAdmin: true, entityType: 'units' },
        },
        {
          path: 'admin/master-data/units/create',
          name: 'AdminUnitsCreatePage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'units' },
        },
        {
          path: 'admin/master-data/units/:id/edit',
          name: 'AdminUnitsEditPage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'units' },
        },
        {
          path: 'admin/master-data/cost-items',
          name: 'AdminCostItemsListPage',
          component: MasterDataListPage,
          meta: { requiresAdmin: true, entityType: 'costItems' },
        },
        {
          path: 'admin/master-data/cost-items/create',
          name: 'AdminCostItemsCreatePage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'costItems' },
        },
        {
          path: 'admin/master-data/cost-items/:id/edit',
          name: 'AdminCostItemsEditPage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'costItems' },
        },
        {
          path: 'admin/master-data/signatories',
          name: 'AdminSignatoriesListPage',
          component: MasterDataListPage,
          meta: { requiresAdmin: true, entityType: 'signatories' },
        },
        {
          path: 'admin/master-data/signatories/create',
          name: 'AdminSignatoriesCreatePage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'signatories' },
        },
        {
          path: 'admin/master-data/signatories/:id/edit',
          name: 'AdminSignatoriesEditPage',
          component: MasterDataFormPage,
          meta: { requiresAdmin: true, entityType: 'signatories' },
        },
        {
          path: 'admin/templates',
          name: 'AdminTemplatesListPage',
          component: TemplateListPage,
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/templates/create',
          name: 'AdminTemplatesCreatePage',
          component: TemplateFormPage,
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/templates/:id/edit',
          name: 'AdminTemplatesEditPage',
          component: TemplateFormPage,
          meta: { requiresAdmin: true },
        },
      ],
    },
    {
      path: '*',
      name: 'Error',
      component: ErrorPage,
    }
  ],
});

router.beforeEach((to, from, next) => {
  if (to.matched.some((record) => record.meta && record.meta.requiresAdmin)) {
    if (!isAuthenticated()) {
      next({ path: '/login' });
      return;
    }

    if (!isAdmin()) {
      const user = getCurrentUser();
      if (user.role === 'manager') {
        next({ path: '/app/main/analytics' });
        return;
      }

      next({ path: '/app/main/analytics' });
      return;
    }
  }

  next();
});

export default router;
