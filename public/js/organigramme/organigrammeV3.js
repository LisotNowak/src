
function escapeForJS(str) {
  if(str == null){
    return '';
  }else{
    return str.replace(/'/g, "\\'");
  }
}

function viewProfil(nom, positionName, tags, mobile, mail, area, imageUrl) {
  document.getElementById("prenomModal").textContent = nom;
  document.getElementById("serviceModal").textContent = positionName;
  document.getElementById("telMobileModal").textContent = mobile;
  document.getElementById("mailModal").textContent = mail;
  document.getElementById("domaineModal").textContent = area;
  document.getElementById("missionModal").textContent = tags;
  if(imageUrl != "../img/default.png"){
    document.getElementById("photoModal").style = "background: url(data:image/jpeg;base64,"+imageUrl+") no-repeat center center ;background-size: cover;";
  }else{
    document.getElementById("photoModal").style = "background: url(/../img/default.png) no-repeat center center ;background-size: cover;";
  }
}

// --- Organigramme dynamique ---
document.addEventListener('DOMContentLoaded', function() {
  let allDataUser = [];
  let dataUser = [];
  let chart;

  function getSubtreeWithAncestors(data, rootId) {
    // Map id -> node
    const idMap = {};
    data.forEach(item => { if(item && item.id) idMap[item.id] = item; });
    // 1. Récupérer tous les descendants (BFS)
    const descendants = [];
    const queue = [rootId];
    while(queue.length > 0) {
      const currentId = queue.shift();
      const node = idMap[currentId];
      if(node && !descendants.includes(node)) {
        descendants.push(node);
        data.forEach(item => {
          if(item.parentId === currentId) queue.push(item.id);
        });
      }
    }
    // 2. Ajouter tous les ancêtres jusqu'à la racine
    let current = idMap[rootId];
    while(current && current.parentId && idMap[current.parentId]) {
      const parent = idMap[current.parentId];
      if(!descendants.includes(parent)) descendants.push(parent);
      current = parent;
    }
    return descendants;
  }

  function renderChart(filteredData) {
    if(chart && chart.destroy) chart.destroy();
    chart = new d3.OrgChart()
      .container('.chart-container')
      .data(filteredData)
      .nodeWidth((d) => 400)
      .initialZoom(0.7)
      .nodeHeight((d) => 185)
      .childrenMargin((d) => 40)
      .compactMarginBetween((d) => 25)
      .compactMarginPair((d) => 80)
      .linkUpdate(function (d, i, arr) 
      {
        d3.select(this)
          .attr('stroke', (d) =>
            d.data._upToTheRootHighlighted ? '#14760D' : '#2CAAE5'
          )
          .attr('stroke-width', (d) =>
            d.data._upToTheRootHighlighted ? 15 : 1.3
          );
        if (d.data._upToTheRootHighlighted) {
          d3.select(this).raise();
        }
      })
      .nodeContent(function (d, i, arr, state) {
        const colors = ['#6E6B6F', '#18A8B6', '#F45754', '#96C62C', '#BD7E16', '#802F74'];
        const color = colors[d.depth % colors.length];
        const imageUrl =
            d.data.imageUrl !== "../img/default.png"
                ? `data:image/jpeg;base64,${d.data.imageUrl}`
                : d.data.imageUrl;
        const hasPositionOrArea = d.data.positionName || d.data.area;
        let res = `
        <div style="padding-top:30px;background-color:none;margin-left:1px;height:${d.height}px;border-radius:2px;overflow:visible">
          <div style="height:${d.height - 32}px;padding-top:0px;background-color:white;border:1px solid lightgray;${
            hasPositionOrArea
              ? `" data-bs-target='#modalInfos' data-bs-toggle='modal' onclick="viewProfil('${d.data.name}','${d.data.positionName}','${escapeForJS(d.data.tags)}','${d.data.mobile}','${d.data.mail}','${d.data.area}','${d.data.imageUrl}')"`
              : `"`
          }>
          <img src="${imageUrl}" style="margin-top:-30px;margin-left:${d.width / 2 - 30}px;border-radius:100px;width:60px;height:60px;" />
          <div style="margin-right:10px;margin-top:15px;float:right"></div>
          <div class="bandeau-user" style="margin-top:-30px;background-color:${color};height:10px;width:${d.width - 2}px;border-radius:1px"></div>
          <div style="padding:20px; padding-top:35px;text-align:center">
              <div style="color:#111672;font-size:16px;font-weight:bold"> ${d.data.name}</div>
              <div style="color:#404040;font-size:16px;margin-top:4px"> ${d.data.tags} </div>
              ${
                d.data.positionName
                  ? `<div style='color:#404040;font-size:16px;margin-top:4px'>
                      <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-diagram-2-fill' viewBox='0 0 16 16'>
                        <path fill-rule='evenodd' d='M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H11a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 5 7h2.5V6A1.5 1.5 0 0 1 6 4.5zm-3 8A1.5 1.5 0 0 1 4.5 10h1A1.5 1.5 0 0 1 7 11.5v1A1.5 1.5 0 0 1 5.5 14h-1A1.5 1.5 0 0 1 3 12.5zm6 0a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 9 12.5z'/>
                      </svg> ${d.data.positionName} </div>`
                  : ""
              }
              ${
                d.data.area
                  ? `<div style='color:#404040;font-size:16px;margin-top:4px'>
                      <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-building-fill' viewBox='0 0 16 16'>
                        <path d='M3 0a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3v-3.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V16h3a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1zm1 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5h1a.5.5 0 0 1 .5-.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5M4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5m2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5h1a.5.5 0 0 1 .5-.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5'/>
                      </svg> ${d.data.area} </div>`
                  : ""
              }
          </div>
          <div style="display:flex;justify-content:space-between;padding-left:15px;padding-right:15px;"></div>
          </div>     
        </div>`;
        return res;
      })
      .render()
      .expandAll();
    document.getElementsByClassName("svg-chart-container").width = "100%";
  }

  function populateRootSelector(data) {
    const select = document.getElementById('rootSelector');
    select.innerHTML = '';
    // Uniquement les personnes avec un id et un nom
    const persons = data.filter(item => item && item.id && item.name);
    persons.forEach(item => {
      const option = document.createElement('option');
      option.value = item.id;
      option.textContent = item.name + (item.positionName ? ' (' + item.positionName + ')' : '');
      select.appendChild(option);
    });
  }

  $.ajax({
    url: "/organigrammeData",
    type: "POST",
    async: false,
    success: function (data) {
      if (!data) {
        console.error("No data received from the server.");
        return;
      }
      jsonString = data.replace(/^\uFEFF/, '');
      const dataJson = JSON.parse(jsonString);
      // Récupère tous les nni pour vérifier l'existence d'un parentId correspondant
      const nniSet = new Set(dataJson.map(item => item.nni));
      // Filtre les éléments en ignorant les critères pour frederic.e.artemis
      const filteredData = dataJson.filter(item => {
        if (item.nni === "frederic.e.artemis") {
          item.parentId = "";
          return true;
        }
        return item.parentId && item.parentId !== "nni" && nniSet.has(item.parentId);
      });
      allDataUser = filteredData;
      populateRootSelector(allDataUser);
      // Par défaut, racine = premier de la liste
      if(allDataUser.length > 0) {
        const rootId = allDataUser[0].id;
        dataUser = getSubtreeWithAncestors(allDataUser, rootId);
        renderChart(dataUser);
        document.getElementById('rootSelector').value = rootId;
      }
    },
    error: function (xhr, status, error) {
      console.error("AJAX request failed:", status, error);
    }
  });

  document.getElementById('rootSelector').addEventListener('change', function() {
    const rootId = this.value;
    dataUser = getSubtreeWithAncestors(allDataUser, rootId);
    renderChart(dataUser);
  });
});

