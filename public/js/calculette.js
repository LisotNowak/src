// Afficher le modal automatiquement au chargement de la page
document.addEventListener("DOMContentLoaded", function () {
    const devWarningModal = new bootstrap.Modal(document.getElementById('devWarningModal'));
    devWarningModal.show();
});

function calcul() {
    // helper : parse float with comma support
    const parseNumber = (s) => {
        if (s === undefined || s === null || s === "") return 0;
        return parseFloat(String(s).replace(',', '.')) || 0;
    };

    let totalHSaisie = 0;
    let totalHNorm = 0;
    let totalH25 = 0;
    let totalHRepComp = 0;
    let totalHCompl = 0;

    // Liste des inputs HSaisie (en ordre du DOM)
    const allHSaisieInputs = Array.from(document.getElementsByClassName("HSaisie"));

    // Réinitialisation de tous les champs calculés
    const allCalculatedFields = document.querySelectorAll(
        ".HNorm, .HS25, .HS50, .HCompl, .HRepComp, .dimancheHRepComp"
    );
    allCalculatedFields.forEach(field => field.value = "");

    const isSaisonnier = document.getElementById("saisonnierCheckbox")?.checked;
    let lastRowWithHSaisie = null;

    // ---- 1) Parcours des lignes ----
    for (let InputHSaisie of allHSaisieInputs) {
        const hSaisie = parseNumber(InputHSaisie.value);

        totalHSaisie += hSaisie;

        if (hSaisie > 0) {
            lastRowWithHSaisie = InputHSaisie.closest("tr");
        }

        if (totalHSaisie > 60) {
            const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
            alertModal.show();
        }

        let jour = InputHSaisie.id.split('H')[0];
        const elHNorm = document.getElementById(jour + "HNorm");
        const elHS25 = document.getElementById(jour + "HS25");
        const elHS50 = document.getElementById(jour + "HS50");

        if (!elHNorm || !elHS25 || !elHS50) continue;

        elHNorm.value = "";
        elHS25.value = "";
        elHS50.value = "";

        if (hSaisie <= 0) {
            // rien
        } else if (totalHSaisie <= 35) {
            elHNorm.value = hSaisie;
            totalHNorm += hSaisie;
        } else if (totalHSaisie <= 43) {
            if (totalHNorm < 35) {
                const normHours = Math.max(0, 35 - totalHNorm);
                const hs25 = Math.max(0, totalHSaisie - 35);
                elHNorm.value = normHours;
                elHS25.value = hs25;
                totalHNorm += normHours;
                totalH25 += hs25;
            } else {
                elHS25.value = hSaisie;
                totalH25 += hSaisie;
            }
        } else {
            if (totalH25 < 8) {
                const remaining25 = Math.max(0, 8 - totalH25);
                const hs25 = Math.min(remaining25, hSaisie);
                const hs50 = Math.max(0, hSaisie - hs25);
                elHS25.value = hs25;
                elHS50.value = hs50;
                totalH25 += hs25;
            } else {
                elHS50.value = hSaisie;
            }
        }
    }

    // ---- Correction : HNorm doit être au moins 35 si totalHSaisie >= 35 ----
    if (totalHSaisie >= 35 && totalHNorm < 35) {
        let manque = 35 - totalHNorm;

        for (let InputHSaisie of allHSaisieInputs) {
            if (manque <= 0) break;

            const jour = InputHSaisie.id.split('H')[0];
            const elHNorm = document.getElementById(jour + "HNorm");

            if (!elHNorm) continue;

            let hNormVal = parseNumber(elHNorm.value);
            let hSaisieVal = parseNumber(InputHSaisie.value);

            // combien on peut rajouter sur ce jour
            const dispo = hSaisieVal - hNormVal;
            if (dispo > 0) {
                const ajout = Math.min(dispo, manque);
                elHNorm.value = (hNormVal + ajout).toFixed(2);
                manque -= ajout;
            }
        }

        totalHNorm = 35;
    }

    // ---- 2) Calculs globaux ----
    if (!isSaisonnier) {
        if (totalHSaisie >= 49 && totalHSaisie <= 56) {
            totalHRepComp = (totalHSaisie - 48) * 0.25;
        } else if (totalHSaisie >= 57) {
            totalHRepComp = (56 - 48) * 0.25;
            totalHRepComp += (totalHSaisie - 56) * 0.5;
        }

        const hDimanche = parseNumber(document.querySelector(".dimancheHSaisie")?.value);
        if (hDimanche > 0) totalHRepComp += hDimanche;

        if (lastRowWithHSaisie) {
            const targets = lastRowWithHSaisie.querySelectorAll(".HRepComp");
            if (targets.length) targets[0].value = totalHRepComp.toFixed(2);
        }
    }

    if (totalHSaisie >= 49) {
        if (totalHSaisie <= 56) {
            totalHCompl = (totalHSaisie - 48) * 0.25;
        } else {
            totalHCompl = (56 - 48) * 0.25;
            totalHCompl += (totalHSaisie - 56) * 0.5;
        }

        if (lastRowWithHSaisie) {
            const targetsCompl = lastRowWithHSaisie.querySelectorAll(".HCompl");
            if (targetsCompl.length) targetsCompl[0].value = totalHCompl.toFixed(2);
        }
    }

    const dimancheHS50El = document.getElementById("dimancheHS50");
    const dimancheSaisieEl = document.getElementsByClassName("dimancheHSaisie")[0];
    if (dimancheHS50El) dimancheHS50El.value = dimancheSaisieEl ? dimancheSaisieEl.value : "";

    const totalEl = document.getElementById("totalHsaisie");
    if (totalEl) totalEl.value = totalHSaisie;
}

