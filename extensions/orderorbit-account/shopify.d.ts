import '@shopify/ui-extensions';

//@ts-ignore
declare module './src/Block.jsx' {
  const shopify:
    | import('@shopify/ui-extensions/customer-account.order-index.block.render').Api
    | import('@shopify/ui-extensions/customer-account.profile.block.render').Api
    | import('@shopify/ui-extensions/customer-account.order-status.block.render').Api;
  const globalThis: { shopify: typeof shopify };
}

//@ts-ignore
declare module './src/ReorderMenu.jsx' {
  const shopify: import('@shopify/ui-extensions/customer-account.order.action.menu-item.render').Api;
  const globalThis: { shopify: typeof shopify };
}

//@ts-ignore
declare module './src/ReorderAction.jsx' {
  const shopify: import('@shopify/ui-extensions/customer-account.order.action.render').Api;
  const globalThis: { shopify: typeof shopify };
}

//@ts-ignore
declare module './src/logic.js' {
  const shopify:
    | import('@shopify/ui-extensions/customer-account.order-index.block.render').Api
    | import('@shopify/ui-extensions/customer-account.profile.block.render').Api
    | import('@shopify/ui-extensions/customer-account.order-status.block.render').Api
    | import('@shopify/ui-extensions/customer-account.order.action.render').Api;
  const globalThis: { shopify: typeof shopify };
}

//@ts-ignore
declare module './src/data.js' {
  const shopify:
    | import('@shopify/ui-extensions/customer-account.order-index.block.render').Api
    | import('@shopify/ui-extensions/customer-account.profile.block.render').Api
    | import('@shopify/ui-extensions/customer-account.order-status.block.render').Api
    | import('@shopify/ui-extensions/customer-account.order.action.menu-item.render').Api
    | import('@shopify/ui-extensions/customer-account.order.action.render').Api;
  const globalThis: { shopify: typeof shopify };
}
