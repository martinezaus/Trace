/* Configuración compatible con Chart.js 2.7.1 instalado. */
const grafico={
    initChartsTareas(creadas,terminadas){return new Chart(document.getElementById('chartTareasAsigTerm').getContext('2d'),{
        type:'line',data:{labels:['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],datasets:[
            {label:'Creadas',data:creadas,borderColor:'#6bd098',backgroundColor:'rgba(107,208,152,.1)',fill:false},
            {label:'Terminadas',data:terminadas,borderColor:'#f17e5d',backgroundColor:'rgba(241,126,93,.1)',fill:false}]},
        options:{responsive:true,maintainAspectRatio:false,legend:{position:'top'},tooltips:{mode:'index',intersect:false},scales:{yAxes:[{ticks:{beginAtZero:true,precision:0}}]}}
    });},
    initCharsTareasOperarios(items){const rows=Object.values(items).filter(x=>Array.isArray(x)&&x.length>=2);return this.circular('chartOperariosTareas','pie',rows.map(x=>x[0]||'Sin nombre'),rows.map(x=>Number(x[1])||0),'right');},
    initCharsTareasPS(terminadas,suspendidas){return this.circular('chartTareasProdSusp','doughnut',['Terminadas','Suspendidas'],[Number(terminadas)||0,Number(suspendidas)||0],'bottom');},
    circular(id,type,labels,values,position){return new Chart(document.getElementById(id).getContext('2d'),{
        type,data:{labels,datasets:[{data:values,backgroundColor:values.map((_,i)=>['#6bd098','#ef8157','#51cbce','#fbc658','#ad82d1'][i%5]),borderWidth:1}]},
        options:{responsive:true,maintainAspectRatio:false,cutoutPercentage:type==='doughnut'?55:0,legend:{position},tooltips:{callbacks:{label(item,data){const value=Number(data.datasets[item.datasetIndex].data[item.index])||0,total=data.datasets[item.datasetIndex].data.reduce((a,b)=>a+Number(b),0);return `${data.labels[item.index]}: ${value} (${total?(value*100/total).toFixed(1):'0.0'}%)`;}}}}
    });}
};
