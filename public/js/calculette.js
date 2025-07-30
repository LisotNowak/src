// Afficher le modal automatiquement au chargement de la page
document.addEventListener("DOMContentLoaded", function () {
    const devWarningModal = new bootstrap.Modal(document.getElementById('devWarningModal'));
    devWarningModal.show();
  });


function calcul() {
    let totalHSaisie = 0;
    let totalHNorm = 0;
    let totalH25 = 0;

    // Réinitialisation de tous les champs calculés
    const allHSaisieInputs = document.getElementsByClassName("HSaisie");
    const allCalculatedFields = document.querySelectorAll(
        ".HNorm, .HS25, .HS50, .HCompl, .HRepComp, .dimancheHRepComp"
    );

    // Réinitialiser les champs calculés
    allCalculatedFields.forEach(field => {
        field.value = "";
    });

    // Parcourir les entrées pour effectuer le calcul
    for (let InputHSaisie of allHSaisieInputs) {
        let hSaisie = parseFloat(InputHSaisie.value);
        if (isNaN(hSaisie)) {
            hSaisie = 0;
        }else{

            totalHSaisie += hSaisie;

            // Vérifier si le total dépasse 60h
            if (totalHSaisie > 60) {
                const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
                alertModal.show();
                // return; // Bloquer l'exécution du reste de la fonction
            }

            let jour = InputHSaisie.id.split('H')[0];

            // Si "Saisonnier" est coché, forcer les champs HRepComp* à 0
            const isSaisonnier = document.getElementById("saisonnierCheckbox")?.checked;

            if (isSaisonnier) {
                const repCompSuffixes = ["HRepComp", "HRepComp10", "HRepComp25", "HRepComp50", "HRepComp100"];
                repCompSuffixes.forEach(suffix => {
                    const field = document.getElementById(jour + suffix);
                    if (field) {
                        field.value = null;
                    }
                });
            }
    
            // Réinitialiser les champs du jour en cours
            document.getElementById(jour + "HS25").value = "";
            document.getElementById(jour + "HS50").value = "";
            document.getElementById(jour + "HCompl").value = "";
    
            // Calcul des heures selon les règles
            if (totalHSaisie > 35) {
                if (totalHSaisie <= 43) {
                    if (totalHNorm < 35) {
                        let normHours = 35 - totalHNorm;
                        document.getElementById(jour + "HNorm").value = normHours;
                        totalHNorm += normHours;
    
                        let hs25 = totalHSaisie - 35;
                        document.getElementById(jour + "HS25").value = hs25;
                        totalH25 += hs25;
                    } else {
                        document.getElementById(jour + "HS25").value = hSaisie;
                        totalH25 += hSaisie;
                    }
                } else {
                    if (totalH25 < 8) {
                        let remaining25 = Math.max(0, 8 - totalH25);
                        document.getElementById(jour + "HS25").value = remaining25;
                        totalH25 += remaining25;
    
                        let hs50 = hSaisie - remaining25;
                        document.getElementById(jour + "HS50").value = hs50;
                    } else {
                        document.getElementById(jour + "HS50").value = hSaisie;
                    }
    
                    if (totalHSaisie >= 49 && totalHSaisie <= 60) {
                            const isSaisonnier = document.getElementById("saisonnierCheckbox")?.checked;

                            if (!isSaisonnier) {
                                document.querySelectorAll('.HRepComp').forEach(field => {
                                    field.value = "";
                                });

                                if (document.getElementById(jour + "HSaisie").value != 0) {
                                    document.getElementById(jour + "HRepComp").value =
                                        (totalHSaisie - 48) * 0.25;
                                }
                            }
                        

    
                        if (totalHSaisie >= 57) {
                            if(document.getElementById(jour+"HCompl").value == ""){
                                document.getElementById(jour+"HCompl").value = 0;
                            }
                            console.log(jour);

                            if(jour == "dimanche"){
                                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + parseFloat((56 - 48) * 0.25);
                                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + parseFloat((totalHSaisie - 56) * 0.5);
                                
                            }else{
                                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + parseFloat((56 - 48) * 0.25);
                                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + parseFloat((totalHSaisie - 56) * 0.5);
                            }
                        }
                    }
                }
            } else {
                document.getElementById(jour + "HNorm").value = hSaisie;
                totalHNorm += hSaisie;
                document.getElementById(jour + "HS25").value = "";
            }
    
    
            if (InputHSaisie.classList.contains('dimancheHSaisie')) {
                document.getElementById(jour+"HRepComp").value = parseFloat(document.getElementById(jour+"HRepComp").value) + hSaisie;
            }
    
    
            document.getElementById("dimancheHS50").value = document.getElementsByClassName("dimancheHSaisie")[0]?.value || null;
    
            document.getElementById("totalHsaisie").value = totalHSaisie;
        }

        
    }
    
    // Appeler la fonction après le calcul
    updateTotalHCompl();
    

}

function updateTotalHCompl() {
    let totalHCompl = 0;
    const allHComplInputs = document.getElementsByClassName("HCompl");

    // Étape 1 : Additionner toutes les valeurs de la colonne "H. Compl."
    for (let input of allHComplInputs) {
        let hComplValue = parseFloat(input.value);
        if (!isNaN(hComplValue)) {
            totalHCompl += hComplValue;
        }
    }

    console.log("Total H. Compl.:", totalHCompl);

    // Étape 2 : Trouver la dernière ligne où "H. Effectuée" est > 0
    const allRows = document.querySelectorAll("tr");
    let lastRowWithHEffectuee = null;

    allRows.forEach(row => {
        let hEffectueeInput = row.querySelector(".HSaisie");
        if (hEffectueeInput) {
            let hEffectueeValue = parseFloat(hEffectueeInput.value);
            if (!isNaN(hEffectueeValue) && hEffectueeValue > 0) {
                lastRowWithHEffectuee = row; // Met à jour la dernière ligne trouvée
            }
        }
    });

    console.log("Dernière ligne avec H. Effectuée:", lastRowWithHEffectuee);

    // Étape 3 : Réinitialiser toutes les valeurs de la colonne "H. Compl."
    for (let input of allHComplInputs) {
        input.value = ""; // Vide toutes les valeurs
    }

    // Étape 4 : Insérer la somme des "H. Compl." dans la dernière ligne trouvée
    if (lastRowWithHEffectuee) {
        let targetInput = lastRowWithHEffectuee.querySelector(".HCompl");
        if (targetInput) {
            targetInput.value = totalHCompl.toFixed(2);
        }
    }
}

