Migrate a DataTable from client-side to server-side processing.

Arguments: $ARGUMENTS should contain: <EntityName> <ControllerPath>

Steps:
1. Read the existing controller and blade view for the entity
2. Create or update the following for server-side DataTables:
   - **Backend**:
     - `app/Http/Requests/<Entity>DataRequest.php` — Form Request for filter validation (filter_* keys)
     - `app/DataTables/Transformers/<Entity>RowTransformer.php` — static transform() returning HTML per column
     - Add `listForDataTables(FilterDTO, DataTablesParamsDTO)` method to repository interface + implementation
     - Add `data()` method to controller using DataTablesRequest::fromRequest() with ORDER_WHITELIST
     - Add POST route for the data endpoint
   - **Frontend**:
     - Update or create JS file using `initServerSideDataTable()` from `resources/js/common/datatables.js`
     - Wire filter form change/submit to reload table
   - **View**:
     - Update blade to use empty `<tbody></tbody>` and `data-datatable-url` attribute
3. Follow the contract in `app/docs/DATATABLES_SERVER_SIDE.md`
4. Register the JS entry in `vite.config.js` if new
5. Run `make assets-build` after frontend changes
6. Run `make qa` to verify PHPStan compliance
