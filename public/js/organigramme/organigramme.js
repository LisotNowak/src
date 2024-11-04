// Tout ce qui touche à l'animation de la page (changement d'état de bouton ect...)
function set_service() 
{
    service = document.getElementById('selectService').value;
    section = document.getElementById('selectSection');
    listeSection = document.getElementsByClassName('optionSelectSection');
    if (service != "") {
        for (i = 0; listeSection[i]; i++) {
            if (listeSection[i].getAttribute('service') == service)
                listeSection[i].hidden = false;
            else
                listeSection[i].hidden = true;
        }
        document.getElementById('selectSection').removeAttribute("disabled");
    } else {
        document.getElementById('none-selected').hidden = false;
        document.getElementById('selectSection').setAttribute("disabled", "");
    }
    document.getElementById('container').innerHTML = '';
    section.value = "";
    set_orga();
}

function set_section() 
{
    document.getElementById('container').innerHTML = '';
    set_orga();
}

// Tout ce qui touche à l'oganigramme / recup de valeur
function set_image_orga()
{
    doc = document.getElementsByTagName('img');
    user = document.getElementsByTagName('user');
    
    for (i = 0; doc[i]; i++) {
        if (doc[i].src != null) {
            src_doc = doc[i].src.split('/')[6];
            for (j = 0; user[j]; j++) {
                tab_user = user[j].innerHTML.split('|');
                if (tab_user[0] == src_doc) {
                    doc[i].src = tab_user[7];
                }
            }
        }
    }
}

function set_orga()
{
    service = document.getElementById('selectService').value;
    if (service == "")
        return;
    section = document.getElementById('selectSection').value;
    if (section == ""){
        set_orga_service(service);
        document.getElementById('none-selected').hidden = false;
    }
    else {
        document.getElementById('none-selected').hidden = true;
        set_orga_section(section, service);
    }
    set_image_orga();
}

function set_height_size(tab) {
    nb = 0;
    for (i = 0; tab[i]; i++) {
        nb++
    }
    return (nb * 100)
    //taille des liens
}

function autre_superieur(str) {
    doc = document.getElementsByTagName('user');
    for (a = 0; doc[a]; a++) {
        temp_user = doc[a].innerHTML.split('|');
        if (temp_user[4] == str) {
            for (b = 0; doc[b]; b++) {
                temp_user_2 = doc[b].innerHTML.split('|');
                if (temp_user_2[4] == temp_user[0]) {
                        return (1);
                    }
            }
        }
    }
    return (0);
}

function set_orga_section(section, service)
{
    // Partie récup users, données ect...
    data_users = [];
    nodes_users = [];
    doc = document.getElementsByTagName('user');

    for (i = 0; doc[i]; i++) {
        user = doc[i].innerHTML.split('|');
        user_image = "../img/default.jpg";
        if (user[3] == section) {
            if (user[7] != "")
                user_image = "../fichiers/annuaire/imgProfil/" + user[0];
            if (user[4] != "" && user[4] != 0)
                for (j = 0; doc[j]; j++) {
                    user2 = doc[j].innerHTML.split('|');
                    if (user2[0] == user[4] && user[3] == user2[3]) {
                        data_users.push([user[4], user[0]]);
                    }
                }
            if (user[4] == "" || user[4] == "0" || user[4] == user[0])
                nodes_users.push({'id': user[0], 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
            else {
                if (autre_superieur(user[0]) == 1)
                    nodes_users.push({'id': user[0], 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
                else
                    nodes_users.push({'id': user[0], 'layout': 'hanging', 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
            }
        }
    }
    height_graph = set_height_size(data_users);

    // Partie utilisation users (Rendu graphique des données ci dessus)
    Highcharts.chart('container', {
        chart: {
            height: height_graph,
            inverted: true,
            click: function(e) {
                document.getElementById('modalInfos').focus();
                //window.location.href='../../profil/' + this.id;
            }
        },
        name: section,
        title: {
            text: 'Organigramme'
        },
    
        accessibility: {
            point: {
                descriptionFormatter: function (point) {
                    var nodeName = point.toNode.name,
                        nodeId = point.toNode.id,
                        nodeDesc = nodeName === nodeId ? nodeName : nodeName + ', ' + nodeId,
                        parentDesc = point.fromNode.id;
                    return point.index + '. ' + nodeDesc + ', reports to ' + parentDesc + '.';
                }
            }
        },
    
        series: [{
            type: 'organization',
            keys: ['from', 'to'],
            name: "",
            data: data_users,
            nodes: nodes_users,
            colorByPoint: false,
            color: '#007ad0',
            dataLabels: {
                color: 'white'
            },
            borderColor: 'white',
            nodeWidth: 100,
            point: {
                events: {
                  click: function() {
                        // window.location.href='../../profil/' + this.id;
                        // Recherche les informations relié à l'id de la case cliqué
                        doc = document.getElementsByTagName('user');
                        i = 0;
                        tab_infos_user = null;
                        for (; doc[i]; i++) {
                            tab_infos_user = doc[i].innerHTML.split('|');
                            if (tab_infos_user[0] == this.id)
                                break;
                        }

                        // Traitement des valeurs
                        if (tab_infos_user[15].length > 0)
                            tab_infos_user[15] = tab_infos_user[15].slice(0, -2);

                        tab_infos_user[16] = tab_infos_user[16].replace(' - ', ' ');

                        // Change le modal en fonction des informations
                        document.getElementById('prenomModal').innerText = this.name;
                        document.getElementById('serviceModal').innerText = tab_infos_user[13];
                        document.getElementById('fonctionModal').innerText = tab_infos_user[5];
                        document.getElementById('specialiteModal').innerText = tab_infos_user[14];
                        document.getElementById('missionsModal').innerText = tab_infos_user[15];
                        document.getElementById('puiModal').innerText = tab_infos_user[11];
                        document.getElementById('telMobileModal').innerText = tab_infos_user[9];
                        document.getElementById('telFixeModal').innerText = tab_infos_user[8];
                        document.getElementById('mailModal').innerText = tab_infos_user[10];
                        document.getElementById('batimentModal').innerText = tab_infos_user[16];
                        document.getElementById('superieurModal').innerText = tab_infos_user[17];
                        document.getElementById('photoModal').src = "../img/default.jpg"
                        if (this.image != "../img/default.jpg")
                            document.getElementById('photoModal').src = tab_infos_user[7];

                        // Affiche le modal
                        $('#modalInfos').modal('show');
                    }
                }
            }
        }],
        tooltip: {
            outside: true
        },
        exporting: {
            allowHTML: true,
            sourceWidth: 1800,
            sourceHeight: 600
        },
        tooltip: {
            enabled: false
        },
    });

    // Enlève un lien vers le site highcharts (donc laisser cette ligne toujours après une génération d'un chart)
    document.getElementsByClassName('highcharts-credits')[0].innerHTML = "";
}

function set_orga_service(service) {
    // Partie récup users, données ect...
    data_users = [];
    nodes_users = [];
    doc = document.getElementsByTagName('user');
    for (i = 0; doc[i]; i++) {
        user = doc[i].innerHTML.split('|');
        user_image = "../img/default.jpg"
        if (user[6] == service) {
            if (user[7] != "")
                user_image = "../fichiers/annuaire/imgProfil/" + user[0];
            if (user[4] != 0 && user[4] != user[0] && user[4] != 0) {
                data_users.push([user[4], user[0]]);
                if (autre_superieur(user[0]) == 1)
                    nodes_users.push({'id': user[0], 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
                else
                    nodes_users.push({'id': user[0], 'layout': 'hanging', 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
            } else {
                if (autre_superieur(user[0]) == 1)
                    nodes_users.push({'id': user[0], 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
                else
                    nodes_users.push({'id': user[0], 'layout': 'hanging', 'name': user[1] + " " + user[2], 'title': user[5], 'image': user_image});
            }
        }
    }
    height_graph = set_height_size(data_users);

    // Partie utilisation users (Rendu graphique des données ci dessus)
 
    Highcharts.chart('container', {
        chart: {
            height: height_graph,
            inverted: true,
        },
        name: service,
        title: {
            text: 'Organigramme'
        },
    
        accessibility: {
            point: {
                descriptionFormatter: function (point) {
                    var nodeName = point.toNode.name,
                        nodeId = point.toNode.id,
                        nodeDesc = nodeName === nodeId ? nodeName : nodeName + ', ' + nodeId,
                        parentDesc = point.fromNode.id;
                    return point.index + '. ' + nodeDesc + ', reports to ' + parentDesc + '.';
                }
            }
        },
    
        series: [{
            type: 'organization',
            keys: ['from', 'to'],
            name: "",
            data: data_users,
            nodes: nodes_users,
            colorByPoint: false,
            color: '#007ad0',
            dataLabels: {
                color: 'white'
            },
            borderColor: 'white',
            nodeWidth: 100,
            point: {
                events: {
                  click: function() {
                        // window.location.href='../../profil/' + this.id;
                        // Recherche les informations relié à l'id de la case cliqué
                        doc = document.getElementsByTagName('user');
                        i = 0;
                        tab_infos_user = null;
                        for (; doc[i]; i++) {
                            tab_infos_user = doc[i].innerHTML.split('|');
                            if (tab_infos_user[0] == this.id)
                                break;
                        }

                        // Traitement des valeurs
                        if (tab_infos_user[15].length > 0)
                            tab_infos_user[15] = tab_infos_user[15].slice(0, -2);

                            tab_infos_user[16] = tab_infos_user[16].replace(' - ', ' ');
 
                        // Change le modal en fonction des informations
                        document.getElementById('prenomModal').innerText = this.name;
                        document.getElementById('serviceModal').innerText = tab_infos_user[13];
                        document.getElementById('fonctionModal').innerText = tab_infos_user[5];
                        document.getElementById('specialiteModal').innerText = tab_infos_user[14];
                        document.getElementById('missionsModal').innerText = tab_infos_user[15];
                        document.getElementById('puiModal').innerText = tab_infos_user[11];
                        document.getElementById('telMobileModal').innerText = tab_infos_user[9];
                        document.getElementById('telFixeModal').innerText = tab_infos_user[8];
                        document.getElementById('mailModal').innerText = tab_infos_user[10];
                        document.getElementById('batimentModal').innerText = tab_infos_user[16];
                        document.getElementById('superieurModal').innerText = tab_infos_user[17];
                        document.getElementById('photoModal').src = "../img/default.jpg"
                        if (this.image != "../img/default.jpg")
                            document.getElementById('photoModal').src = tab_infos_user[7];

                        // Affiche le modal
                        $('#modalInfos').modal('show');
                    }
                }
            }
        }],
        exporting: {
            allowHTML: true,
            sourceWidth: 1800,
            sourceHeight: 600
        },
        tooltip: {
            enabled: false
        },
    });
    // Enlève un lien vers le site highcharts (donc laisser cette ligne toujours après une génération d'un chart)
    document.getElementsByClassName('highcharts-credits')[0].innerHTML = "";
}