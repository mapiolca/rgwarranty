# CHANGELOG MODULE GESTIONNAIRERG FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0

Initial version

## 1.1.0

* Ajout du module de gestion des retenues de garantie (cycles, cockpit, paiement, PDF et emails).

## 1.1.1

* Nettoyage des anciens fichiers legacy retainedwarranty.

## 1.1.2

* Correction du chargement de la bibliothèque du module dans l'administration.

## 1.1.3

* Remplacement du FormSetup pour compatibilité Dolibarr 21.

## 1.1.4

* Suppression du descripteur module legacy modGestionnaireRG.

## 1.1.5

* Correction des chemins d'inclusion du module rgwarranty dans l'interface.

## 1.1.6

* Correction du chargement SQL et des chemins de module rgwarranty.

## 1.1.7

* Correction des inclusions facture dans la fiche cycle RG.

## 1.1.8

* Chargement des bibliothèques via chemins relatifs et garde sur la classe modRGWarranty.

## 1.1.9

* Suppression des références à l'ancien dossier du module.

## 1.1.10

* Compatibilité du chargement de la classe PDF de base pour le modèle RG (Dolibarr 21+).

## 1.1.11

* Fallback supplémentaire de chargement de doc_pdf pour plusieurs chemins Dolibarr.

## 1.1.12

* Correction du chemin de chargement de la bibliothèque rgwarranty dans le modèle PDF.

## 1.1.13

* Fallback DOL_DOCUMENT_ROOT pour charger doc_pdf et garantir ModelePDF.
