var chart;
//name,imageUrl,area,profileUrl,office,tags,isLoggedUser,positionName,id,parentId,size
//https://raw.githubusercontent.com/bumbeishvili/sample-data/main/org.csv



var dataUser

document.addEventListener('DOMContentLoaded', (event) => {

  console.log("okkkkkkkkkk");

  $.ajax({
    url: "/organigrammeData",
    type: "POST",
    async: false,
    success: function (data) {
        jsonString = data.replace(/^\uFEFF/, '');
        const dataJson = JSON.parse(jsonString);
        // Récupère tous les nni pour vérifier l'existence d'un parentId correspondant
        const nniSet = new Set(dataJson.map(item => item.nni));

        // Filtre les éléments en ignorant les critères pour frederic.e.artemis
        const filteredData = dataJson.filter(item => {
            if (item.nni === "frederic.e.artemis") {
                item.parentId = ""; // Attribue une valeur vide à parentId pour frederic.e.artemis
                return true; // Garde l'objet dans filteredData
            }
            // Applique les autres critères pour les autres items
            return item.parentId && item.parentId !== "nni" && nniSet.has(item.parentId);
        });

        dataUser = filteredData;
        console.log(dataUser);
    },
  });



    chart = new d3.OrgChart()
      .container('.chart-container')
      .data(dataUser)
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

        if (d.data._upToTheRootHighlighted)
        {
          d3.select(this).raise();
        }
      })
      .nodeContent(function (d, i, arr, state) 
      {

        const colors = ['#6E6B6F','#18A8B6','#F45754','#96C62C','#BD7E16','#802F74'];
        const color = colors[d.depth % colors.length];
        const imageDim = 80;
        const lightCircleDim = 95;
        const outsideCircleDim = 110;
        res = `
        <div style="padding-top:30px;background-color:none;margin-left:1px;height:${
          d.height
        }px;border-radius:2px;overflow:visible">
          <div style="height:${
            d.height - 32
          }px;padding-top:0px;background-color:white;border:1px solid lightgray;" data-bs-target="#modalInfos" data-bs-toggle="modal" onclick="viewProfil('${d.data.nni}','${d.data.imageUrl}')">

          <img src=" ${
              d.data.imageUrl
            }" style="margin-top:-30px;margin-left:${d.width / 2 - 30}px;border-radius:100px;width:60px;height:60px;" />

            <div style="margin-right:10px;margin-top:15px;float:right">${
            ""
            }</div>
            
            <div style="margin-top:-30px;background-color:${color};height:10px;width:${
              d.width - 2
            }px;border-radius:1px"></div>

            <div style="padding:20px; padding-top:35px;text-align:center">
                <div style="color:#111672;font-size:16px;font-weight:bold"> ${
                  d.data.name
                } </div>
                <div style="color:#404040;font-size:16px;margin-top:4px"> ${
                  d.data.positionName
                } </div>
                <div style="color:#404040;font-size:16px;margin-top:4px"> ${
                  d.data.tags
                } </div>
                <div style="color:#404040;font-size:16px;margin-top:4px"> ${
                  d.data.area
                } </div>
            </div> 
            <div style="display:flex;justify-content:space-between;padding-left:15px;padding-right:15px;">
              `
              // if(d.data.mobile != null)
              // {
              //   res += `<div > Mobile :  ${d.data.mobile}</div>`;
              // }
              // if(d.data.mail != null)
              // {
              //   res += `<div > Mail : ${d.data.mail}</div>`;
              // }
              res += `        </div>
              </div>     
        </div>`;
    return res;
      })
      .render();

      document.getElementsByClassName("svg-chart-container").width = "100%";

      
    function changeURLOrganigramme()
    {
      var serviceChoisi = document.getElementById("allService").options[document.getElementById("allService").selectedIndex].textContent;

      if(serviceChoisi == "Ensemble des services")
      {
        document.location = '/annuaire/organigramme';
      }
      else
      {
        document.location = '/annuaire/organigramme/'+serviceChoisi;
      }
    }

});

function viewProfil(nni, imgUrl)
    {
      $.ajax({
        url: "/annuaire/organigramme-profil/profil",
        type: "POST",
        async: false,
        data:
        {
          'nni' : nni,
        },
        success: function (data) 
        {
          document.getElementById("prenomModal").textContent = data[0].prenom + " " + data[0].nom;
          document.getElementById("serviceModal").textContent = data[0].service + " " + data[0].section;
          document.getElementById("superieurModal").textContent = data[0].superieur;

          if(data[0].fonction != null)
          {
            document.getElementById("fonctionModal").textContent = data[0].fonction;
          }
          else
          {
            document.getElementById("fonctionModal").textContent = "";
          }

          if(data[0].specialite != null)
          {
            document.getElementById("specialiteModal").textContent = data[0].specialite;
          }
          else
          {
            document.getElementById("specialiteModal").textContent = "";
          }

          if(data[0].telMobile != null)
          {
            // On ajoute un espace tous les 2 caractères pour le num tél mobile
            document.getElementById("telMobileModal").textContent = data[0].telMobile.replace(/(.{2})(?!$)/g,"$1 ");
          }
          else
          {
            document.getElementById("telMobileModal").textContent = "";
          }
          
          if(data[0].telConnect != null)
          {
            document.getElementById("telConnectModal").textContent = data[0].telConnect;
          }
          else
          {
            document.getElementById("telConnectModal").textContent = "";
          }

          if(data[0].mail != null)
          {
            document.getElementById("mailModal").textContent = data[0].mail;
          }
          else
          {
            document.getElementById("mailModal").textContent = "";
          }

          if(data[0].batiment != null && data[0].etage != null && data[0].bureau != null)
          {
            document.getElementById("batimentModal").textContent = data[0].batiment + " " + data[0].etage + " - " + data[0].bureau;
          }
          else
          {
            if(data[0].batiment != null && data[0].etage != null)
            {
              document.getElementById("batimentModal").textContent = data[0].batiment + " " + data[0].etage;
            }
            else
            {
              document.getElementById("batimentModal").textContent = "";
            }
          }
          
          if(data[0].listeMissions != null)
          {
            document.getElementById("missionsModal").textContent = data[0].listeMissions;
          }
          else
          {
            document.getElementById("missionsModal").textContent = "";
          }

          if(data[0].pui != null)
          {
            document.getElementById("puiModal").textContent = data[0].pui;
          }
          else
          {
            document.getElementById("puiModal").textContent = "";
          }
          
          document.getElementById("photoModal").style = "background: url(" + imgUrl +") no-repeat center center ;background-size: cover;"; 
          document.getElementById("btnModifier").href = "/profil/" + nni;

          document.getElementById("btnOrganigramme").href = "/annuaire/organigramme/" + data[0].service;
          document.getElementById("btnOrganigrammeEquipe").hidden="true";
          document.getElementById("btnOrganigramme").hidden="true";
          

        },
      });
    }

