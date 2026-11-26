/* Reportes: catálogo y reglas compartidos con el servidor. */
(function () {
    "use strict";
    $(function () {
        if (!document.getElementById("contenidoReportes")) return;
        const catalog = JSON.parse(document.getElementById("reportCatalog").textContent);
        const csrf = document.getElementById("reportCsrf").value;
        let table = null, snapshot = null, searchController = null, version = 0, exporting = false;
        const $form = $("#advancedReportForm");
        const operators = {gt:"Mayor que",gte:"Mayor o igual",lt:"Menor que",lte:"Menor o igual",eq:"Igual a",between:"Entre (inclusive)",contains:"Contiene",missing:"Sin datos",present:"Con datos"};
        const language = {
            emptyTable:"No hay datos", info:"Mostrando _START_ a _END_ de _TOTAL_ registros", infoEmpty:"Sin registros",
            infoFiltered:"(de _MAX_ registros consultados)", lengthMenu:"Mostrar _MENU_", search:"Buscar en resultados:",
            zeroRecords:"Ninguna fila coincide con la búsqueda", paginate:{first:"Primera",last:"Última",next:"Siguiente",previous:"Anterior"}
        };
        function alertMessage(message, kind = "danger") {
            $("#reportAlerts").empty().append($("<div>").addClass("alert alert-" + kind).text(message));
        }
        function localDate(date) {
            return date.getFullYear() + "-" + String(date.getMonth()+1).padStart(2,"0") + "-" + String(date.getDate()).padStart(2,"0");
        }
        function dates() {
            const today = new Date();
            $("#dateFrom").val(localDate(new Date(today.getFullYear(),today.getMonth(),1)));
            $("#dateTo").val(localDate(today));
        }
        function enabledExports() {
            const ready = !!snapshot && !exporting && table && table.rows({search:"applied"}).count() > 0;
            $("#exportExcel,#exportPDF").prop("disabled", !ready);
        }
        function invalidate() {
            version++;
            if (searchController) searchController.abort();
            searchController = null; snapshot = null;
            $("#loadingSpinner").prop("hidden",true);
            $("#searchReports").prop("disabled",false);
            $("#resultsTableContainer").prop("hidden",true);
            $("#emptyState").show().text("Configure los filtros y busque datos.");
            $("#resultsCount").text("Filtros pendientes de consultar.");
            enabledExports();
        }
        function option(select, value, label) { select.append($("<option>").val(value).text(label)); }
        function changeType() {
            invalidate();
            const config = catalog[$("#reportType").val()];
            const status = $("#statusFilter").empty(), condition = $("#additionalCondition").empty();
            option(status,"","Todos"); option(condition,"","Sin condición");
            status.prop("disabled", !config || Object.keys(config.statuses).length === 0);
            condition.prop("disabled", !config);
            if (config) {
                Object.entries(config.statuses).forEach(([key,label]) => option(status,key,label));
                config.columns.filter(c => !["estado","fecha","fin"].includes(c.field)).forEach(c => option(condition,c.field,c.title));
            }
            $("#reportPeriod").text(config ? "Período: " + config.period + "." : "");
            $("#reportNote").text(config ? config.note : "");
            changeCondition();
        }
        function selectedColumn() {
            return catalog[$("#reportType").val()]?.columns.find(c => c.field === $("#additionalCondition").val());
        }
        function changeCondition() {
            const col = selectedColumn(), select = $("#conditionOperator").empty();
            $("#conditionValue,#conditionValueTo").val("");
            select.prop("disabled",!col);
            if (col) {
                const numeric = ["duration","integer","percent"].includes(col.type);
                (numeric ? ["gt","gte","lt","lte","eq","between","missing","present"] : ["contains","eq","missing","present"])
                    .forEach(op => option(select,op,operators[op]));
            }
            changeOperator();
        }
        function changeOperator() {
            const col = selectedColumn(), op = $("#conditionOperator").val();
            const noValue = !col || ["missing","present"].includes(op);
            const between = !!col && op === "between";
            $("#conditionMaxGroup").prop("hidden",!between);
            $("#conditionValue").prop({disabled:noValue,required:!noValue});
            $("#conditionValueTo").prop({disabled:!between,required:between});
            const help = !col || noValue ? "" : col.type === "duration" ? "Duración HHH:MM:SS, admite más de 24 horas. Ejemplo: 025:30:00."
                : col.type === "integer" ? "Número entero desde 0." : col.type === "percent" ? "Porcentaje decimal, admite coma o punto. No se limita a 100 %. Ejemplo: 85,5."
                : "Texto sin distinguir mayúsculas y minúsculas.";
            $("#conditionHelp").text(help);
            $("#conditionValue,#conditionValueTo").attr("placeholder",col?.type === "duration" ? "025:30:00" : col?.type === "percent" ? "85,5" : "");
        }
        function formData() {
            const data = new FormData();
            ["reportType","dateFrom","dateTo","statusFilter","additionalCondition","conditionOperator","conditionValue","conditionValueTo"].forEach(id => data.append(id,$("#"+id).val() ?? ""));
            data.append("csrf",csrf);
            return data;
        }
        async function jsonResponse(response) {
            let data;
            try { data = await response.json(); } catch (_) { throw new Error("El servidor devolvió una respuesta inválida. No se descargó ningún archivo."); }
            if (!response.ok || !data.success) {
                throw new Error(data.message || "No se pudo completar la operación.");
            }
            return data;
        }
        const escapeHtml = value => $("<div>").text(value).html();
        function display(value,type) {
            if (value === null || value === undefined || value === "") return "Sin datos";
            if (type === "duration") {
                const seconds = Math.abs(Math.trunc(Number(value)));
                return (Number(value)<0?"-":"") + String(Math.floor(seconds/3600)).padStart(2,"0") + ":" + String(Math.floor(seconds%3600/60)).padStart(2,"0") + ":" + String(seconds%60).padStart(2,"0");
            }
            if (type === "percent") return (Number(value) > 0 ? "+" : "") + Number(value).toLocaleString("es-AR",{minimumFractionDigits:2,maximumFractionDigits:2})+" %";
            if (type === "integer") return Number(value).toLocaleString("es-AR",{maximumFractionDigits:0});
            if (type === "date") {
                const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
                return match ? match[3]+"/"+match[2]+"/"+match[1]+" "+match[4] : String(value);
            }
            return String(value);
        }
        function render(data) {
            if (table) { table.destroy(); table=null; }
            $("#resultsTable").empty().append("<thead></thead><tbody></tbody>");
            snapshot=data.snapshot;
            $("#resultsCount").text(data.total+" registros encontrados.");
            $("#reportApplied").text(data.description+" Consulta: "+data.created);
            $("#emptyState").toggle(data.total===0).text("No se encontraron datos para estos filtros.");
            $("#resultsTableContainer").prop("hidden",data.total===0);
            if (!data.total) { enabledExports(); return; }
            table=$("#resultsTable").DataTable({
                data:data.data, columns:data.columns.map(c => ({
                    data:c.field, title:c.title, defaultContent:"",
                    render:(value,mode) => mode === "display" ? escapeHtml(display(value,c.type))
                        : mode === "filter" ? display(value,c.type) : value ?? (["duration","integer","percent"].includes(c.type) ? -Infinity : ""),
                    className:["duration","integer","percent"].includes(c.type) ? "text-right" : ""
                })),
                order:[], pageLength:25, lengthMenu:[25,50,100], language, dom:"lfrtip", autoWidth:false
            });
            $("#resultsTable_filter input").attr("maxlength",500);
            $("#resultsTable").off("draw.dt.reportes").on("draw.dt.reportes",enabledExports);
            enabledExports();
        }
        $form.on("submit",async function(e) {
            e.preventDefault();
            if (!this.reportValidity()) return;
            if ($("#dateFrom").val() > $("#dateTo").val()) { alertMessage("Desde no puede ser posterior a Hasta.","warning"); return; }
            invalidate(); const requestVersion=version;
            searchController=new AbortController();
            $("#reportAlerts").empty(); $("#emptyState").hide();
            $("#loadingSpinner").prop("hidden",false); $("#searchReports").prop("disabled",true);
            try {
                const response=await fetch("api/reportes.php",{method:"POST",body:formData(),signal:searchController.signal});
                const data=await jsonResponse(response);
                if(requestVersion===version) render(data);
            } catch(error) {
                if(error.name!=="AbortError") {
                    alertMessage(error.message);
                    if(requestVersion===version) { $("#emptyState").show().text("No se pudo completar la consulta."); $("#resultsCount").text("Error de consulta."); }
                }
            } finally {
                if(requestVersion===version) { searchController=null; $("#loadingSpinner").prop("hidden",true); $("#searchReports").prop("disabled",false); enabledExports(); }
            }
        });
        $form.on("input change","input,select",invalidate);
        $("#reportType").on("change",changeType);
        $("#additionalCondition").on("change",changeCondition);
        $("#conditionOperator").on("change",changeOperator);
        $("#clearFilters").on("click",() => { $form[0].reset(); dates(); changeType(); $("#reportAlerts").empty(); });
        async function exportReport(format) {
            if(!snapshot || !table || exporting) return;
            const body=new FormData(); body.append("csrf",csrf); body.append("snapshot",snapshot);
            body.append("export_format",format); body.append("tableSearch",table.search());
            body.append("rows",JSON.stringify(table.rows({search:"applied",order:"applied"}).indexes().toArray()));
            exporting=true; enabledExports(); alertMessage("Generando archivo…","info");
            try {
                const response=await fetch("include/pages/exportar.php",{method:"POST",body});
                const expected=format==="pdf"?"application/pdf":"text/csv";
                if(!response.ok || !(response.headers.get("Content-Type")||"").includes(expected)) {
                    await jsonResponse(response); throw new Error("El formato recibido no corresponde al archivo solicitado.");
                }
                const blob=await response.blob();
                if(format==="pdf" && await blob.slice(0,5).text()!=="%PDF-") throw new Error("El servidor no generó un PDF válido.");
                const url=URL.createObjectURL(blob), link=document.createElement("a");
                const match=(response.headers.get("Content-Disposition")||"").match(/filename="([^"]+)"/);
                link.download=match?match[1]:"reporte."+format; link.href=url; document.body.appendChild(link); link.click(); link.remove();
                setTimeout(()=>URL.revokeObjectURL(url),30000);
                alertMessage("Archivo generado correctamente.","success");
            } catch(error) { alertMessage(error.message); } finally { exporting=false; enabledExports(); }
        }
        $("#exportExcel").on("click",()=>exportReport("csv"));
        $("#exportPDF").on("click",()=>exportReport("pdf"));
        dates(); $("#reportType").val("ordenes"); changeType();
    });
})();
