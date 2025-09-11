// Afficher le modal automatiquement au chargement de la page
document.addEventListener("DOMContentLoaded", function () {
    const devWarningModal = new bootstrap.Modal(document.getElementById('devWarningModal'));
    devWarningModal.show();
});

function calcul() {
    let totalHSaisie = 0;
    let totalHNorm = 0;
    let totalH25 = 0;
    let totalHRepComp = 0; // somme globale HRepComp

    // Réinitialisation de tous les champs calculés
    const allHSaisieInputs = document.getElementsByClassName("HSaisie");
    const allCalculatedFields = document.querySelectorAll(
        ".HNorm, .HS25, .HS50, .HRepComp, .dimancheHRepComp"
    );

    allCalculatedFields.forEach(field => {
        field.value = "";
    });

    const isSaisonnier = document.getElementById("saisonnierCheckbox")?.checked;

    let lastRowWithHSaisie = null; // pour savoir où mettre le total final

    // Parcourir les entrées pour effectuer le calcul
    for (let InputHSaisie of allHSaisieInputs) {
        let hSaisie = parseFloat(InputHSaisie.value);
        if (isNaN(hSaisie)) {
            hSaisie = 0;
        } else {
            totalHSaisie += hSaisie;

            if (hSaisie > 0) {
                lastRowWithHSaisie = InputHSaisie.closest("tr"); // mémorise la dernière ligne
            }

            // Vérifier si le total dépasse 60h
            if (totalHSaisie > 60) {
                const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
                alertModal.show();
            }

            let jour = InputHSaisie.id.split('H')[0];

            // Réinitialiser les champs du jour
            document.getElementById(jour + "HS25").value = "";
            document.getElementById(jour + "HS50").value = "";

            // Calcul heures normales et supplémentaires
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
                }
            } else {
                document.getElementById(jour + "HNorm").value = hSaisie;
                totalHNorm += hSaisie;
                document.getElementById(jour + "HS25").value = "";
            }
        }
    }

    // === Nouveau calcul HRepComp global ===
    if (!isSaisonnier) {
        if (totalHSaisie >= 49 && totalHSaisie <= 56) {
            totalHRepComp = (totalHSaisie - 48) * 0.25;
        } else if (totalHSaisie >= 57 && totalHSaisie <= 60) {
            totalHRepComp = (56 - 48) * 0.25;         // 49h à 56h
            totalHRepComp += (totalHSaisie - 56) * 0.5; // 57h+
        }

        // Ajouter toutes les heures du dimanche
        let hDimanche = parseFloat(document.querySelector(".dimancheHSaisie")?.value) || 0;
        if (hDimanche > 0) {
            totalHRepComp += hDimanche;
        }

        // Mettre uniquement dans la dernière ligne avec HSaisie > 0
        if (lastRowWithHSaisie) {
            let targetInput = lastRowWithHSaisie.querySelector(".HRepComp");
            if (targetInput) {
                targetInput.value = totalHRepComp.toFixed(2);
            }
        }
    }

    // Heures dimanche en HS50
    document.getElementById("dimancheHS50").value =
        document.getElementsByClassName("dimancheHSaisie")[0]?.value || null;

    // Total heures saisies
    document.getElementById("totalHsaisie").value = totalHSaisie;
}
