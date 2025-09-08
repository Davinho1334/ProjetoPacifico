# 🏫 SchoolFlow — Administração escolar mais eficiente, prática e organizada

> **SchoolFlow** é um sistema web que centraliza a gestão de estudantes e processos escolares. Com interface intuitiva, edição rápida e gráficos visuais, gestores e administradores acompanham em tempo real dados pessoais, desempenho/estatus e benefícios (bolsas, cargas horárias), reduzindo burocracias e erros.

---

## ✨ Principais recursos

* **Visão 360° do aluno**: dados cadastrais, curso/turno/série, status, empresa, carga semanal e bolsa.
* **Filtros inteligentes**: por status, curso, escola e busca por nome/CPF/RA.
* **Edição rápida**: modal para atualizar RA, curso, turno, série, status, empresa e datas de trabalho.
* **Painéis e gráficos**: distribuição por status/curso e média de bolsa (Chart.js).
* **Exportações**: geração de **PDF** e **Excel** (DomPDF e PhpSpreadsheet).
* **Sessão de administrador**: login, controle de acesso e logout.
* **Arquitetura moderna**: front-end leve (HTML/CSS/JS) + back-end PHP com Composer, pronto para escalar.

---

## 🖼️ Screens & Fluxos

* **Portal**: acesso rápido para *Aluno* (cadastro) e *Administrador* (login).
* **Login (Admin)** → **Dashboard Administrativo**: tabela, filtros, cards de status e ações (exportar PDF/Excel, abrir dashboard do aluno).
* **Cadastro do Aluno**: formulário simples e validado.
* **Dashboard do Aluno**: visão individual (cards + mini-gráficos) e visão geral (tabela + gráficos).

> Dica: suba o projeto localmente e acesse `index.html` para navegar pelos fluxos.

---

## 🏗️ Arquitetura

**Frontend**

* `index.html`, `aluno.html`, `admin_login.html`, `admin_dashboard.html`, `aluno_dashboard.html`
* `css/style.css` — estilo moderno, responsivo
* `javascript/script.js` — utilitários e validações (ex.: CPF)
* **Chart.js** via CDN — gráficos de status/curso/bolsa

**Backend (PHP)**

* Endpoints (pasta `php/`, exemplos):

  * `admin_login.php`, `logout.php`
  * `get_students.php` (lista/1 aluno), `edit_student.php` (edição)
  * `get_companies.php` (apoio aos selects)
  * `export_pdf.php`, `export_excel.php`
* **Composer**:

  * `dompdf/dompdf` — geração de PDF
  * `phpoffice/phpspreadsheet` — planilhas Excel

> Observação: os endpoints PHP acima são referenciados pelo front — implemente-os conforme o modelo abaixo ou adapte aos seus serviços.

---

## 🖥️ Linguagens utilizadas

* **HTML5**
* **CSS3**
* **JavaScript**
* **PHP 8.1+**
* **MySQL/MariaDB**

---
