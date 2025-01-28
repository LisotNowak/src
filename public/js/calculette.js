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
                        document.querySelectorAll('.HRepComp').forEach(field => {
                            field.value = "";
                        });
    
                        if (document.getElementById(jour + "HSaisie").value != 0) {
                            document.getElementById(jour + "HRepComp").value =
                                (totalHSaisie - 48) * 0.25;
                        }
    
                        if (totalHSaisie >= 57) {
                            document.getElementById(jour + "HCompl").value = parseFloat(
                                document.getElementById(jour + "HCompl").value || 0
                            ) + parseFloat((56 - 48) * 0.25) +
                                parseFloat((totalHSaisie - 56) * 0.5);
                        }
                    }
                }
            } else {
                document.getElementById(jour + "HNorm").value = hSaisie;
                totalHNorm += hSaisie;
                document.getElementById(jour + "HS25").value = "";
            }
    
    
            if (InputHSaisie.classList.contains('dimancheHSaisie')) {
                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + hSaisie;
            }
    
    
            document.getElementById("dimancheHS50").value = document.getElementsByClassName("dimancheHSaisie")[0].value;
    
            document.getElementById("totalHsaisie").value = totalHSaisie;
        }

        
    }
}
